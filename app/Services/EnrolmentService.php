<?php

namespace App\Services;

use App\Models\MEnrol;
use App\Models\MUserEnrolment;
use App\Models\MCohort;
use App\Models\MCohortMember;
use App\Models\Payment;
use App\Models\User;
use App\Models\Course;
use Illuminate\Support\Facades\Log;

class EnrolmentService
{
    /**
     * Enrol a user manually into a course (spec §6.3).
     */
    public function manualEnrol(string $courseId, string $userId, ?string $roleId = null, ?string $timeend = null): MUserEnrolment
    {
        // Find or create a manual enrol instance for this course
        $enrol = MEnrol::firstOrCreate(
            [
                'course_id' => $courseId,
                'enrol' => MEnrol::METHOD_MANUAL,
            ],
            [
                'status' => MEnrol::STATUS_ENABLED,
                'role_id' => $roleId,
                'sortorder' => 0,
            ]
        );

        return MUserEnrolment::firstOrCreate(
            ['enrol_id' => $enrol->id, 'user_id' => $userId],
            [
                'status' => MUserEnrolment::STATUS_ACTIVE,
                'timestart' => now(),
                'timeend' => $timeend,
            ]
        );
    }

    /**
     * Self-enrolment with key (spec §6.3).
     */
    public function selfEnrol(string $courseId, string $userId, ?string $enrolkey = null): MUserEnrolment
    {
        $enrol = MEnrol::where('course_id', $courseId)
            ->where('enrol', MEnrol::METHOD_SELF)
            ->where('status', MEnrol::STATUS_ENABLED)
            ->first();

        if (!$enrol) {
            throw new \Exception('Self-enrolment is not available for this course.');
        }

        // Check enrolment key if set
        if ($enrol->customchar1 && $enrol->customchar1 !== $enrolkey) {
            throw new \Exception('Invalid enrolment key.');
        }

        // Check enrol period
        if ($enrol->enrolstartdate && now()->lt($enrol->enrolstartdate)) {
            throw new \Exception('Enrolment has not opened yet.');
        }
        if ($enrol->enrolenddate && now()->gt($enrol->enrolenddate)) {
            throw new \Exception('Enrolment has closed.');
        }

        return MUserEnrolment::firstOrCreate(
            ['enrol_id' => $enrol->id, 'user_id' => $userId],
            [
                'status' => MUserEnrolment::STATUS_ACTIVE,
                'timestart' => now(),
                'timeend' => $enrol->enrolperiod ? now()->addSeconds($enrol->enrolperiod) : null,
            ]
        );
    }

    /**
     * Payment enrolment — activated only after confirmed fee (spec §6.2, §22).
     */
    public function paymentEnrol(string $courseId, string $userId): MUserEnrolment
    {
        $enrol = MEnrol::firstOrCreate(
            [
                'course_id' => $courseId,
                'enrol' => MEnrol::METHOD_PAYMENT,
            ],
            [
                'status' => MEnrol::STATUS_ENABLED,
                'sortorder' => 0,
            ]
        );

        // Check if payment is confirmed
        $feeItem = $enrol->customchar2 ?? 'Registration';
        $hasPayment = Payment::where('student_id', $userId)
            ->where('status', 'CONFIRMED')
            ->where('fee_item', $feeItem)
            ->exists();

        $status = $hasPayment ? MUserEnrolment::STATUS_ACTIVE : MUserEnrolment::STATUS_SUSPENDED;

        return MUserEnrolment::firstOrCreate(
            ['enrol_id' => $enrol->id, 'user_id' => $userId],
            [
                'status' => $status,
                'timestart' => now(),
            ]
        );
    }

    /**
     * Activate enrolment when payment is confirmed (spec §22 — payment_confirmed event).
     * This is the observer that reacts to payment confirmation.
     */
    public function onPaymentConfirmed(string $userId, ?string $feeItem = null): void
    {
        // Find all payment enrolments for this user that are suspended
        $suspendedEnrolments = MUserEnrolment::where('user_id', $userId)
            ->where('status', MUserEnrolment::STATUS_SUSPENDED)
            ->whereHas('enrol', function ($q) {
                $q->where('enrol', MEnrol::METHOD_PAYMENT);
            })
            ->get();

        foreach ($suspendedEnrolments as $enrolment) {
            $enrolment->update(['status' => MUserEnrolment::STATUS_ACTIVE]);
            Log::info("Enrolment activated for user {$userId} after payment confirmation", [
                'enrolment_id' => $enrolment->id,
            ]);
        }

        // Also upgrade applicant to student if registration fee confirmed
        if ($feeItem && in_array($feeItem, ['Registration', 'Admission'])) {
            $user = User::find($userId);
            if ($user && $user->role === 'APPLICANT') {
                $user->update(['role' => 'STUDENT']);
                Log::info("User {$userId} upgraded from APPLICANT to STUDENT after payment confirmation");
            }
        }
    }

    /**
     * Suspend a user's enrolment (spec §6.3).
     */
    public function suspendEnrolment(string $enrolId, string $userId): bool
    {
        return MUserEnrolment::where('enrol_id', $enrolId)
            ->where('user_id', $userId)
            ->update(['status' => MUserEnrolment::STATUS_SUSPENDED]) > 0;
    }

    /**
     * Cohort sync — enrol all cohort members into a course (spec §8.2).
     */
    public function cohortSync(string $cohortId, string $courseId, ?string $roleId = null): int
    {
        $cohort = MCohort::with('members')->findOrFail($cohortId);

        $enrol = MEnrol::firstOrCreate(
            [
                'course_id' => $courseId,
                'enrol' => MEnrol::METHOD_COHORT,
                'customint1' => $cohortId,
            ],
            [
                'status' => MEnrol::STATUS_ENABLED,
                'role_id' => $roleId,
                'sortorder' => 0,
            ]
        );

        $count = 0;
        foreach ($cohort->members as $member) {
            MUserEnrolment::firstOrCreate(
                ['enrol_id' => $enrol->id, 'user_id' => $member->user_id],
                ['status' => MUserEnrolment::STATUS_ACTIVE, 'timestart' => now()]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get enrolled users for a course (spec §6.3).
     */
    public function getEnrolledUsers(string $courseId)
    {
        $enrolIds = MEnrol::where('course_id', $courseId)->pluck('id');
        return MUserEnrolment::whereIn('enrol_id', $enrolIds)
            ->where('status', MUserEnrolment::STATUS_ACTIVE)
            ->with(['user', 'enrol'])
            ->get();
    }

    /**
     * Check if a user is enrolled in a course.
     */
    public function isEnrolled(string $userId, string $courseId): bool
    {
        $enrolIds = MEnrol::where('course_id', $courseId)->pluck('id');
        return MUserEnrolment::whereIn('enrol_id', $enrolIds)
            ->where('user_id', $userId)
            ->where('status', MUserEnrolment::STATUS_ACTIVE)
            ->exists();
    }
}
