<?php

namespace App\Services;

use App\Models\MCertificateTemplate;
use App\Models\MCertificateIssue;
use App\Models\MCourseCompletion;
use App\Models\MGradeGrade;
use App\Models\MGradeItem;
use App\Models\MNotification;
use App\Models\MCohortMember;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    /**
     * Generate a unique verification code (spec §2.4).
     */
    public function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (MCertificateIssue::where('code', $code)->exists());
        return $code;
    }

    /**
     * Generate SHA-256 hash for tamper checking (spec §2.4).
     */
    public function generateHash(string $code, string $userId, string $templateId, ?string $grade, string $issuedAt): string
    {
        $payload = implode('|', [$code, $userId, $templateId, $grade ?? '', $issuedAt]);
        return hash('sha256', $payload);
    }

    /**
     * Check eligibility before issuing (spec §1.2).
     */
    public function checkEligibility(string $userId, string $templateId, ?string $courseId = null): array
    {
        $template = MCertificateTemplate::findOrFail($templateId);

        // Check if already issued (unless reissue)
        $existing = MCertificateIssue::where('template_id', $templateId)
            ->where('user_id', $userId)
            ->where('status', MCertificateIssue::STATUS_ISSUED)
            ->exists();
        if ($existing) {
            return ['eligible' => false, 'reason' => 'Already issued. Use reissue to replace.'];
        }

        // Check fees cleared if required
        if ($template->requires_fees_cleared) {
            $hasDebt = DB::table('debts')
                ->where('student_id', $userId)
                ->where('balance', '>', 0)
                ->exists();
            if ($hasDebt) {
                return ['eligible' => false, 'reason' => 'Outstanding debt must be cleared before issuance.'];
            }
        }

        // Check trigger-specific criteria
        switch ($template->trigger_type) {
            case MCertificateTemplate::TRIGGER_COURSE_COMPLETION:
                if ($courseId) {
                    $completed = MCourseCompletion::where('course_id', $courseId)
                        ->where('user_id', $userId)
                        ->where('status', 'COMPLETE')
                        ->exists();
                    if (!$completed) {
                        return ['eligible' => false, 'reason' => 'Course not completed.'];
                    }
                }
                break;

            case MCertificateTemplate::TRIGGER_GRADE_THRESHOLD:
                $config = $template->trigger_config ?? [];
                $minGrade = $config['grade_min'] ?? 0;
                if ($courseId) {
                    $gradeItem = MGradeItem::where('course_id', $courseId)
                        ->where('itemtype', 'course')
                        ->first();
                    if ($gradeItem) {
                        $grade = MGradeGrade::where('itemid', $gradeItem->id)
                            ->where('user_id', $userId)
                            ->first();
                        if (!$grade || !$grade->finalgrade || $grade->finalgrade < $minGrade) {
                            return ['eligible' => false, 'reason' => 'Grade threshold not met.'];
                        }
                    }
                }
                break;

            case MCertificateTemplate::TRIGGER_PROGRAM_COMPLETION:
                // Check all required courses completed
                $config = $template->trigger_config ?? [];
                $programId = $config['program_id'] ?? null;
                if ($programId) {
                    $courses = DB::table('courses')->where('program_id', $programId)->pluck('id');
                    foreach ($courses as $cid) {
                        $completed = MCourseCompletion::where('course_id', $cid)
                            ->where('user_id', $userId)
                            ->where('status', 'COMPLETE')
                            ->exists();
                        if (!$completed) {
                            return ['eligible' => false, 'reason' => 'Not all program courses completed.'];
                        }
                    }
                }
                break;
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Issue a certificate (spec §1.3).
     */
    public function issue(string $templateId, string $userId, string $issuedBy, ?string $courseId = null, ?string $programId = null, ?string $grade = null, ?string $classification = null): MCertificateIssue
    {
        $template = MCertificateTemplate::findOrFail($templateId);

        // Get grade from gradebook if not provided
        if (!$grade && $courseId) {
            $grade = $this->getCourseGrade($courseId, $userId);
        }

        $code = $this->generateCode();
        $issuedAt = now()->toIso8601String();
        $hash = $this->generateHash($code, $userId, $templateId, $grade, $issuedAt);

        // Generate serial number
        $serialNumber = MCertificateIssue::where('template_id', $templateId)->max('serial_number') + 1;

        $issue = MCertificateIssue::create([
            'template_id' => $templateId,
            'user_id' => $userId,
            'course_id' => $courseId,
            'program_id' => $programId,
            'code' => $code,
            'hash' => $hash,
            'grade' => $grade,
            'classification' => $classification,
            'issued_by' => $issuedBy,
            'issued_at' => now(),
            'status' => MCertificateIssue::STATUS_ISSUED,
            'serial_number' => $serialNumber,
        ]);

        // Notify user
        MNotification::create([
            'user_id' => $userId,
            'component' => 'mod_certificate',
            'event_type' => 'certificate_issued',
            'subject' => 'Certificate Issued: ' . $template->name,
            'body' => "Your certificate has been issued. Verification code: {$code}",
            'context_url' => '/dashboard/certificates',
        ]);

        Log::info('Certificate issued', [
            'issue_id' => $issue->id,
            'template_id' => $templateId,
            'user_id' => $userId,
            'code' => $code,
        ]);

        return $issue;
    }

    /**
     * Bulk issue to a cohort or program (spec §6).
     */
    public function bulkIssue(string $templateId, string $issuedBy, ?string $cohortId = null, ?string $programId = null): array
    {
        $template = MCertificateTemplate::findOrFail($templateId);
        $issued = [];
        $skipped = [];

        // Get users from cohort or program enrolment
        if ($cohortId) {
            $userIds = MCohortMember::where('cohort_id', $cohortId)->pluck('user_id');
        } elseif ($programId) {
            $userIds = DB::table('m_user_enrolments')
                ->join('m_enrol', 'm_user_enrolments.enrol_id', '=', 'm_enrol.id')
                ->join('courses', 'm_enrol.course_id', '=', 'courses.id')
                ->where('courses.program_id', $programId)
                ->where('m_user_enrolments.status', 'ACTIVE')
                ->pluck('m_user_enrolments.user_id')
                ->unique();
        } else {
            return ['issued' => [], 'skipped' => [], 'message' => 'No target specified.'];
        }

        foreach ($userIds as $userId) {
            $eligibility = $this->checkEligibility($userId, $templateId);
            if ($eligibility['eligible']) {
                try {
                    $issue = $this->issue($templateId, $userId, $issuedBy, null, $programId);
                    $issued[] = $issue;
                } catch (\Exception $e) {
                    $skipped[] = ['user_id' => $userId, 'reason' => $e->getMessage()];
                }
            } else {
                $skipped[] = ['user_id' => $userId, 'reason' => $eligibility['reason']];
            }
        }

        return [
            'issued' => $issued,
            'skipped' => $skipped,
            'total_issued' => count($issued),
            'total_skipped' => count($skipped),
        ];
    }

    /**
     * Revoke a certificate (spec §6).
     */
    public function revoke(string $issueId, string $reason): MCertificateIssue
    {
        $issue = MCertificateIssue::findOrFail($issueId);
        $issue->update([
            'status' => MCertificateIssue::STATUS_REVOKED,
            'revoke_reason' => $reason,
        ]);

        // Notify user
        MNotification::create([
            'user_id' => $issue->user_id,
            'component' => 'mod_certificate',
            'event_type' => 'certificate_revoked',
            'subject' => 'Certificate Revoked',
            'body' => "Your certificate ({$issue->code}) has been revoked. Reason: {$reason}",
        ]);

        return $issue->fresh();
    }

    /**
     * Reissue a certificate (spec §6).
     */
    public function reissue(string $issueId, string $issuedBy): MCertificateIssue
    {
        $oldIssue = MCertificateIssue::findOrFail($issueId);

        // Mark old as reissued
        $oldIssue->update(['status' => MCertificateIssue::STATUS_REISSUED]);

        // Create new issue
        return $this->issue(
            $oldIssue->template_id,
            $oldIssue->user_id,
            $issuedBy,
            $oldIssue->course_id,
            $oldIssue->program_id,
            $oldIssue->grade,
            $oldIssue->classification
        );
    }

    /**
     * Public verification (spec §5).
     */
    public function verify(string $code): array
    {
        $issue = MCertificateIssue::where('code', $code)->first();

        // Log verification attempt
        DB::table('m_certificate_verifications')->insert([
            'id' => Str::uuid(),
            'code' => $code,
            'ip' => request()->ip(),
            'result' => $issue ? ($issue->status === 'ISSUED' ? 'VALID' : 'REVOKED') : 'INVALID',
            'verified_at' => now(),
        ]);

        if (!$issue) {
            return ['valid' => false, 'status' => 'NOT_FOUND'];
        }

        if ($issue->status === 'REVOKED') {
            return [
                'valid' => false,
                'status' => 'REVOKED',
                'name' => $issue->user->full_name,
                'program' => $issue->template->name,
                'type' => $issue->template->type,
                'issued_at' => $issue->issued_at,
            ];
        }

        return [
            'valid' => true,
            'status' => 'VALID',
            'name' => $issue->user->full_name,
            'program' => $issue->template->name,
            'type' => $issue->template->type,
            'grade' => $issue->grade,
            'classification' => $issue->classification,
            'issued_at' => $issue->issued_at->toDateString(),
            'code' => $issue->code,
        ];
    }

    /**
     * Generate transcript from gradebook (spec §7).
     */
    public function generateTranscript(string $studentId, ?string $academicYear = null, ?string $semester = null): array
    {
        $user = \App\Models\User::findOrFail($studentId);

        $query = DB::table('results')
            ->where('student_id', $studentId);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }
        if ($semester) {
            $query->where('semester', $semester);
        }

        $results = $query->get();
        $courses = [];
        $totalMarks = 0;
        $count = 0;

        foreach ($results as $result) {
            $course = \App\Models\Course::find($result->course_id);
            $courses[] = [
                'course_code' => $course?->code,
                'course_title' => $course?->title,
                'ca_marks' => $result->ca_marks,
                'exam_marks' => $result->exam_marks,
                'total' => $result->total,
                'grade' => $result->grade,
                'academic_year' => $result->academic_year,
                'semester' => $result->semester,
            ];
            $totalMarks += $result->total;
            $count++;
        }

        $average = $count > 0 ? round($totalMarks / $count, 2) : 0;

        return [
            'student' => [
                'name' => $user->full_name,
                'email' => $user->email,
            ],
            'courses' => $courses,
            'average' => $average,
            'total_courses' => $count,
            'generated_at' => now()->toDateString(),
        ];
    }

    /**
     * Evaluate auto-issue triggers (spec §1.3).
     */
    public function evaluateTriggers(string $userId, string $event, ?string $courseId = null): void
    {
        $templates = MCertificateTemplate::where('is_active', true)
            ->where('trigger_type', '!=', MCertificateTemplate::TRIGGER_MANUAL)
            ->get();

        foreach ($templates as $template) {
            $shouldEvaluate = match ($event) {
                'course_completed' => $template->trigger_type === MCertificateTemplate::TRIGGER_COURSE_COMPLETION
                    || $template->trigger_type === MCertificateTemplate::TRIGGER_PROGRAM_COMPLETION,
                'grade_updated' => $template->trigger_type === MCertificateTemplate::TRIGGER_GRADE_THRESHOLD,
                'competency_proficient' => $template->trigger_type === MCertificateTemplate::TRIGGER_COMPETENCY,
                default => false,
            };

            if ($shouldEvaluate) {
                $eligibility = $this->checkEligibility($userId, $template->id, $courseId);
                if ($eligibility['eligible']) {
                    $this->issue($template->id, $userId, $userId, $courseId);
                }
            }
        }
    }

    /**
     * Get course grade for a user.
     */
    private function getCourseGrade(string $courseId, string $userId): ?string
    {
        $gradeItem = MGradeItem::where('course_id', $courseId)
            ->where('itemtype', 'course')
            ->first();

        if (!$gradeItem) return null;

        $grade = MGradeGrade::where('itemid', $gradeItem->id)
            ->where('user_id', $userId)
            ->first();

        if (!$grade || !$grade->finalgrade) return null;

        $percentage = ($grade->finalgrade / $gradeItem->grademax) * 100;

        return match (true) {
            $percentage >= 80 => 'Distinction',
            $percentage >= 65 => 'Credit',
            $percentage >= 50 => 'Pass',
            default => 'Fail',
        };
    }
}
