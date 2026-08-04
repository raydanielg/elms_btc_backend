<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Payment;
use App\Models\Application;
use App\Models\Debt;
use App\Models\Result;
use App\Models\Program;
use App\Models\Announcement;
use App\Models\OnlineClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'STUDENT') {
            return $this->success([
                'enrolled_courses' => $user->enrolledCourses()->count(),
                'completed_courses' => $user->enrolledCourses()->wherePivot('status', 'COMPLETED')->count(),
                'pending_assignments' => Assignment::whereIn('course_id', $user->enrolledCourses()->pluck('courses.id'))
                    ->whereDoesntHave('submissions', function ($q) use ($user) {
                        $q->where('student_id', $user->id);
                    })
                    ->count(),
                'my_submissions' => $user->submissions()->count(),
            ]);
        }

        if ($user->role === 'TUTOR') {
            $courseIds = $user->teachingCourses()->pluck('courses.id');
            $studentIds = Enrollment::whereIn('course_id', $courseIds)->pluck('student_id')->unique();
            return $this->success([
                'teaching_courses' => $user->teachingCourses()->count(),
                'students_taught' => $studentIds->count(),
                'total_assignments' => Assignment::whereIn('course_id', $courseIds)->count(),
                'total_submissions' => AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                    $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
                })->count(),
                'pending_submissions' => AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                    $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
                })->whereNull('marks')->count(),
                'upcoming_classes' => OnlineClass::whereIn('course_id', $courseIds)
                    ->where('scheduled_at', '>', now())->count(),
            ]);
        }

        if ($user->role === 'APPLICANT') {
            $application = Application::where('applicant_id', $user->id)->latest()->first();
            return $this->success([
                'application_status' => $application?->status ?? 'NOT_SUBMITTED',
                'program_applied' => $application ? Program::find($application->program_id)?->name : null,
                'mode_applied' => $application?->mode ?? null,
                'has_pay_slip' => !empty($application?->pay_slip_url),
            ]);
        }

        if ($user->role === 'STAFF') {
            return $this->success([
                'announcements_count' => Announcement::where('scope', 'GLOBAL')->count(),
                'unread_messages' => 0,
                'upcoming_events' => 0,
            ]);
        }

        if ($user->role === 'PRINCIPAL') {
            $totalCollected = Payment::where('status', 'CONFIRMED')->sum('amount');
            $totalExpected = Debt::sum('amount_due');
            $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0;
            $passRate = Result::where('grade', '!=', 'F')->count();
            $totalResults = Result::count();
            $passPercentage = $totalResults > 0 ? round(($passRate / $totalResults) * 100, 1) : 0;

            return $this->success([
                'total_students' => User::where('role', 'STUDENT')->count(),
                'total_tutors' => User::where('role', 'TUTOR')->count(),
                'applications_accepted' => Application::where('status', 'ACCEPTED')->count(),
                'applications_pending' => Application::where('status', 'SUBMITTED')->count(),
                'applications_rejected' => Application::where('status', 'REJECTED')->count(),
                'pass_rate' => $passPercentage,
                'fee_collection_rate' => $collectionRate,
                'total_collected' => $totalCollected,
                'total_outstanding' => Debt::where('balance', '>', 0)->sum('balance'),
            ]);
        }

        if ($user->role === 'REGISTRAR') {
            $coursesWithoutTutor = Course::whereDoesntHave('tutors')->where('is_active', true)->count();
            return $this->success([
                'new_applications' => Application::where('status', 'SUBMITTED')->whereDate('created_at', '>=', now()->startOfMonth())->count(),
                'under_review' => Application::where('status', 'SUBMITTED')->count(),
                'accepted' => Application::where('status', 'ACCEPTED')->count(),
                'rejected' => Application::where('status', 'REJECTED')->count(),
                'enrolled_this_term' => Enrollment::whereDate('enrolled_at', '>=', now()->startOfMonth())->count(),
                'courses_without_tutor' => $coursesWithoutTutor,
                'total_courses' => Course::count(),
                'total_students' => User::where('role', 'STUDENT')->count(),
            ]);
        }

        if ($user->role === 'ACCOUNTANT') {
            return $this->success([
                'total_collected' => Payment::where('status', 'CONFIRMED')->sum('amount'),
                'pending_verification' => Payment::where('status', 'PENDING')->count(),
                'outstanding_debts' => Debt::where('balance', '>', 0)->sum('balance'),
                'confirmations_today' => Payment::where('status', 'CONFIRMED')->whereDate('updated_at', today())->count(),
                'total_debtors' => Debt::where('balance', '>', 0)->count(),
            ]);
        }

        // SUPER_ADMIN — full breakdown
        return $this->success([
            'total_users' => User::count(),
            'total_students' => User::where('role', 'STUDENT')->count(),
            'total_tutors' => User::where('role', 'TUTOR')->count(),
            'total_staff' => User::whereNotIn('role', ['STUDENT', 'TUTOR', 'APPLICANT'])->count(),
            'total_applicants' => User::where('role', 'APPLICANT')->count(),
            'total_programs' => Program::count(),
            'total_courses' => Course::count(),
            'active_courses' => Course::where('is_active', true)->count(),
            'total_enrollments' => Enrollment::count(),
            'pending_applications' => Application::where('status', 'SUBMITTED')->count(),
            'pending_payments' => Payment::where('status', 'PENDING')->count(),
            'confirmed_payments' => Payment::where('status', 'CONFIRMED')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'storage_used' => 0,
        ]);
    }

    public function chartData(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'STUDENT') {
            $courseIds = $user->enrolledCourses()->pluck('courses.id');
            $assignments = Assignment::whereIn('course_id', $courseIds)->get();
            $submissions = $user->submissions()->get();

            // Monthly submissions trend (last 6 months)
            $monthlySubmissions = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlySubmissions[] = [
                    'month' => $month->format('M'),
                    'submissions' => $user->submissions()
                        ->whereYear('submitted_at', $month->year)
                        ->whereMonth('submitted_at', $month->month)
                        ->count(),
                    'graded' => $user->submissions()
                        ->whereYear('submitted_at', $month->year)
                        ->whereMonth('submitted_at', $month->month)
                        ->whereNotNull('marks')
                        ->count(),
                ];
            }

            return $this->success([
                'monthly_trend' => $monthlySubmissions,
                'assignment_status' => [
                    ['name' => 'Pending', 'value' => $assignments->count() - $submissions->count(), 'fill' => '#f59e0b'],
                    ['name' => 'Submitted', 'value' => $submissions->whereNull('marks')->count(), 'fill' => '#3b82f6'],
                    ['name' => 'Graded', 'value' => $submissions->whereNotNull('marks')->count(), 'fill' => '#10b981'],
                ],
                'course_progress' => $user->enrolledCourses()->get()->map(function ($c) {
                    return [
                        'name' => $c->code,
                        'title' => $c->title,
                        'progress' => $c->pivot->status === 'COMPLETED' ? 100 : ($c->pivot->status === 'IN_PROGRESS' ? 50 : 0),
                    ];
                }),
            ]);
        }

        if ($user->role === 'TUTOR') {
            $courseIds = $user->teachingCourses()->pluck('courses.id');

            // Monthly submissions trend
            $monthlySubmissions = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlySubmissions[] = [
                    'month' => $month->format('M'),
                    'submissions' => AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                        $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
                    })->whereYear('submitted_at', $month->year)->whereMonth('submitted_at', $month->month)->count(),
                    'graded' => AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                        $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
                    })->whereYear('submitted_at', $month->year)->whereMonth('submitted_at', $month->month)->whereNotNull('marks')->count(),
                ];
            }

            $totalSubmissions = AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
            })->count();
            $pendingGrading = AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
            })->whereNull('marks')->count();

            return $this->success([
                'monthly_trend' => $monthlySubmissions,
                'assignment_status' => [
                    ['name' => 'Graded', 'value' => $totalSubmissions - $pendingGrading, 'fill' => '#10b981'],
                    ['name' => 'Pending Grading', 'value' => $pendingGrading, 'fill' => '#f59e0b'],
                ],
                'course_progress' => $user->teachingCourses()->get()->map(function ($c) {
                    $studentCount = $c->enrollments()->count();
                    return [
                        'name' => $c->code,
                        'title' => $c->title,
                        'progress' => $studentCount,
                    ];
                }),
            ]);
        }

        if ($user->role === 'PRINCIPAL') {
            $monthlyEnrollments = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyEnrollments[] = [
                    'month' => $month->format('M'),
                    'enrollments' => Enrollment::whereYear('enrolled_at', $month->year)
                        ->whereMonth('enrolled_at', $month->month)->count(),
                ];
            }

            $monthlyPayments = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyPayments[] = [
                    'month' => $month->format('M'),
                    'collected' => Payment::where('status', 'CONFIRMED')
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)->sum('amount'),
                    'outstanding' => Debt::whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)->sum('balance'),
                ];
            }

            $programEnrolment = Program::withCount(['courses'])->get()->map(function ($p) {
                return [
                    'name' => $p->name,
                    'value' => Enrollment::whereIn('course_id', Course::where('program_id', $p->id)->pluck('id'))->count(),
                    'fill' => '#' . substr(md5($p->id), 0, 6),
                ];
            })->toArray();

            return $this->success([
                'monthly_trend' => $monthlyEnrollments,
                'monthly_payments' => $monthlyPayments,
                'program_enrolment' => $programEnrolment,
                'academic_performance' => [
                    ['name' => 'Pass', 'value' => Result::where('grade', '!=', 'F')->count(), 'fill' => '#10b981'],
                    ['name' => 'Fail', 'value' => Result::where('grade', 'F')->count(), 'fill' => '#ef4444'],
                ],
            ]);
        }

        if ($user->role === 'ACCOUNTANT') {
            $monthlyPayments = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyPayments[] = [
                    'month' => $month->format('M'),
                    'confirmed' => Payment::where('status', 'CONFIRMED')
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)->sum('amount'),
                    'pending' => Payment::where('status', 'PENDING')
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)->sum('amount'),
                ];
            }

            $debtAging = [
                ['name' => '0-30 days', 'value' => Debt::where('balance', '>', 0)->where('due_date', '>=', now()->subDays(30))->sum('balance'), 'fill' => '#10b981'],
                ['name' => '31-60 days', 'value' => Debt::where('balance', '>', 0)->where('due_date', '<', now()->subDays(30))->where('due_date', '>=', now()->subDays(60))->sum('balance'), 'fill' => '#f59e0b'],
                ['name' => '60+ days', 'value' => Debt::where('balance', '>', 0)->where('due_date', '<', now()->subDays(60))->sum('balance'), 'fill' => '#ef4444'],
            ];

            return $this->success([
                'monthly_payments' => $monthlyPayments,
                'debt_aging' => $debtAging,
            ]);
        }

        if ($user->role === 'REGISTRAR') {
            $monthlyApplications = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyApplications[] = [
                    'month' => $month->format('M'),
                    'submitted' => Application::where('status', 'SUBMITTED')->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                    'accepted' => Application::where('status', 'ACCEPTED')->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                ];
            }

            $applicantsPerCourse = Course::withCount(['enrollments'])->where('is_active', true)->orderByDesc('enrollments_count')->take(10)->get()->map(function ($c) {
                return ['name' => $c->code, 'title' => $c->title, 'value' => $c->enrollments_count];
            })->toArray();

            return $this->success([
                'monthly_trend' => $monthlyApplications,
                'applicants_per_course' => $applicantsPerCourse,
            ]);
        }

        if ($user->role === 'APPLICANT' || $user->role === 'STAFF') {
            return $this->success([]);
        }

        // Admin
        $monthlyEnrollments = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyEnrollments[] = [
                'month' => $month->format('M'),
                'enrollments' => Enrollment::whereYear('enrolled_at', $month->year)
                    ->whereMonth('enrolled_at', $month->month)->count(),
                'submissions' => AssignmentSubmission::whereYear('submitted_at', $month->year)
                    ->whereMonth('submitted_at', $month->month)->count(),
            ];
        }

        $monthlyPayments = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyPayments[] = [
                'month' => $month->format('M'),
                'confirmed' => Payment::where('status', 'CONFIRMED')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)->count(),
                'pending' => Payment::where('status', 'PENDING')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)->count(),
            ];
        }

        return $this->success([
            'monthly_trend' => $monthlyEnrollments,
            'monthly_payments' => $monthlyPayments,
            'user_distribution' => [
                ['name' => 'Students', 'value' => User::where('role', 'STUDENT')->count(), 'fill' => '#3b82f6'],
                ['name' => 'Tutors', 'value' => User::where('role', 'TUTOR')->count(), 'fill' => '#10b981'],
                ['name' => 'Staff', 'value' => User::whereNotIn('role', ['STUDENT', 'TUTOR', 'APPLICANT'])->count(), 'fill' => '#f59e0b'],
            ],
            'course_status' => [
                ['name' => 'Active', 'value' => Course::where('is_active', true)->count(), 'fill' => '#10b981'],
                ['name' => 'Inactive', 'value' => Course::where('is_active', false)->count(), 'fill' => '#ef4444'],
            ],
        ]);
    }

    public function calendar(Request $request)
    {
        $user = $request->user();
        $start = $request->get('start', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('end', now()->endOfMonth()->format('Y-m-d'));

        $events = [];

        if ($user->role === 'STUDENT') {
            $courseIds = $user->enrolledCourses()->pluck('courses.id');
            $assignments = Assignment::whereIn('course_id', $courseIds)
                ->whereBetween('due_at', [$start, $end])
                ->get();
            foreach ($assignments as $a) {
                $events[] = [
                    'id' => $a->id,
                    'title' => 'Assignment: ' . $a->title,
                    'date' => $a->due_at,
                    'type' => 'assignment',
                ];
            }
            $classes = \App\Models\OnlineClass::whereIn('course_id', $courseIds)
                ->whereBetween('scheduled_at', [$start, $end])
                ->get();
            foreach ($classes as $c) {
                $events[] = [
                    'id' => $c->id,
                    'title' => 'Class: ' . $c->title,
                    'date' => $c->scheduled_at,
                    'type' => 'class',
                ];
            }
        } elseif ($user->role === 'TUTOR') {
            $courseIds = $user->teachingCourses()->pluck('courses.id');
            $assignments = Assignment::whereIn('course_id', $courseIds)
                ->whereBetween('due_at', [$start, $end])
                ->get();
            foreach ($assignments as $a) {
                $events[] = [
                    'id' => $a->id,
                    'title' => 'Due: ' . $a->title,
                    'date' => $a->due_at,
                    'type' => 'assignment',
                ];
            }
            $classes = \App\Models\OnlineClass::whereIn('course_id', $courseIds)
                ->whereBetween('scheduled_at', [$start, $end])
                ->get();
            foreach ($classes as $c) {
                $events[] = [
                    'id' => $c->id,
                    'title' => 'Class: ' . $c->title,
                    'date' => $c->scheduled_at,
                    'type' => 'class',
                ];
            }
        } else {
            // Admin - show all upcoming classes and assignment due dates
            $assignments = Assignment::whereBetween('due_at', [$start, $end])->get();
            foreach ($assignments as $a) {
                $events[] = [
                    'id' => $a->id,
                    'title' => 'Assignment Due: ' . $a->title,
                    'date' => $a->due_at,
                    'type' => 'assignment',
                ];
            }
            $classes = \App\Models\OnlineClass::whereBetween('scheduled_at', [$start, $end])->get();
            foreach ($classes as $c) {
                $events[] = [
                    'id' => $c->id,
                    'title' => 'Class: ' . $c->title,
                    'date' => $c->scheduled_at,
                    'type' => 'class',
                ];
            }
        }

        // Add announcements
        $announcements = \App\Models\Announcement::whereBetween('created_at', [$start, $end])
            ->where('is_pinned', true)
            ->get();
        foreach ($announcements as $a) {
            $events[] = [
                'id' => $a->id,
                'title' => 'Announcement: ' . $a->title,
                'date' => $a->created_at,
                'type' => 'announcement',
            ];
        }

        return $this->success($events);
    }

    public function recentActivity(Request $request)
    {
        $user = $request->user();
        $activities = [];

        if ($user->role === 'STUDENT') {
            $courses = $user->enrolledCourses()->latest('enrollments.enrolled_at')->take(5)->get();
            foreach ($courses as $c) {
                $activities[] = [
                    'title' => $c->title,
                    'type' => 'course',
                    'status' => $c->pivot->status,
                    'date' => $c->pivot->enrolled_at,
                ];
            }
            $submissions = $user->submissions()->latest('submitted_at')->take(3)->get();
            foreach ($submissions as $s) {
                $assignment = $s->assignment;
                $activities[] = [
                    'title' => $assignment?->title ?? 'Assignment',
                    'type' => 'submission',
                    'status' => $s->marks !== null ? 'Graded (' . $s->marks . '/' . $assignment?->max_marks . ')' : 'Submitted',
                    'date' => $s->submitted_at,
                ];
            }
        } elseif ($user->role === 'TUTOR') {
            $courseIds = $user->teachingCourses()->pluck('courses.id');
            $submissions = AssignmentSubmission::whereIn('assignment_id', function ($q) use ($courseIds) {
                $q->select('id')->from('assignments')->whereIn('course_id', $courseIds);
            })->latest('submitted_at')->take(5)->get();
            foreach ($submissions as $s) {
                $student = $s->student;
                $assignment = $s->assignment;
                $activities[] = [
                    'title' => ($student?->full_name ?? 'Student') . ' submitted ' . ($assignment?->title ?? 'assignment'),
                    'type' => 'submission',
                    'status' => $s->marks !== null ? 'Graded' : 'Pending',
                    'date' => $s->submitted_at,
                ];
            }
            $courses = $user->teachingCourses()->latest('updated_at')->take(3)->get();
            foreach ($courses as $c) {
                $activities[] = [
                    'title' => $c->title,
                    'type' => 'course',
                    'status' => $c->is_active ? 'Active' : 'Inactive',
                    'date' => $c->updated_at,
                ];
            }
        } else {
            // Admin
            $enrollments = Enrollment::with(['student', 'course'])->latest('enrolled_at')->take(4)->get();
            foreach ($enrollments as $e) {
                $activities[] = [
                    'title' => ($e->student?->full_name ?? 'Student') . ' enrolled in ' . ($e->course?->title ?? 'course'),
                    'type' => 'enrollment',
                    'status' => $e->status,
                    'date' => $e->enrolled_at,
                ];
            }
            $payments = Payment::latest('created_at')->take(3)->get();
            foreach ($payments as $p) {
                $activities[] = [
                    'title' => 'Payment #' . $p->id,
                    'type' => 'payment',
                    'status' => $p->status,
                    'date' => $p->created_at,
                ];
            }
            $users = User::latest('created_at')->take(3)->get();
            foreach ($users as $u) {
                $activities[] = [
                    'title' => 'New user: ' . $u->full_name,
                    'type' => 'user',
                    'status' => $u->role,
                    'date' => $u->created_at,
                ];
            }
        }

        // Sort by date desc and take 8
        usort($activities, function ($a, $b) {
            return strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? '');
        });

        return $this->success(array_slice($activities, 0, 8));
    }
}
