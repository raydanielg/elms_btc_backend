<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Models\Application;
use App\Models\User;
use App\Models\Payment;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\ApplicationReviewedNotification;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Application::with(['applicant', 'program', 'reviewer']);

        if ($request->has('program')) {
            $query->where('program_id', $request->program);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate($request->get('limit', 20));

        return $this->paginated($applications);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'mode' => 'required|in:ODL,DAY,BRD',
            'form_data' => 'sometimes|array',
            'pay_slip_url' => 'sometimes|string|max:255',
        ]);

        $application = Application::create([
            'applicant_id' => $request->user()->id,
            'program_id' => $data['program_id'],
            'mode' => $data['mode'],
            'form_data' => $data['form_data'] ?? null,
            'pay_slip_url' => $data['pay_slip_url'] ?? null,
            'status' => 'SUBMITTED',
        ]);

        $this->audit('submit_application', 'Application', $application->id, ['program' => $application->program?->name]);

        // Notify applicant via email
        try {
            $applicant = $request->user();
            $programName = $application->program?->name ?? 'Unknown';
            Notification::route('mail', $applicant->email)
                ->notify(new ApplicationSubmittedNotification($programName, $data['mode']));
        } catch (\Exception $e) {
            \Log::error('Application submitted email failed: ' . $e->getMessage());
        }

        return $this->success($application->load(['applicant', 'program']), 201);
    }

    public function show(Request $request, string $id)
    {
        $application = Application::with(['applicant', 'program', 'reviewer'])->findOrFail($id);

        if ($request->user()->role === 'APPLICANT' && $application->applicant_id !== $request->user()->id) {
            return $this->error('FORBIDDEN', 'You can only view your own applications.', 403);
        }

        return $this->success($application);
    }

    public function status(Request $request, string $id)
    {
        $application = Application::findOrFail($id);

        if ($request->user()->role === 'APPLICANT' && $application->applicant_id !== $request->user()->id) {
            return $this->error('FORBIDDEN', 'You can only view your own applications.', 403);
        }

        return $this->success(['status' => $application->status]);
    }

    public function myApplications(Request $request)
    {
        $applications = Application::with(['applicant', 'program', 'reviewer'])
            ->where('applicant_id', $request->user()->id)
            ->latest()
            ->get();

        return $this->success($applications);
    }

    public function updatePaySlip(Request $request, string $id)
    {
        $data = $request->validate([
            'pay_slip_url' => 'required|string|max:255',
        ]);

        $application = Application::findOrFail($id);

        if ($application->applicant_id !== $request->user()->id) {
            return $this->error('FORBIDDEN', 'You can only update your own applications.', 403);
        }

        $application->update(['pay_slip_url' => $data['pay_slip_url']]);

        return $this->success($application->load(['applicant', 'program']));
    }

    public function review(Request $request, string $id)
    {
        $request->validate([
            'decision' => 'required|in:ACCEPTED,REJECTED',
        ]);

        $application = Application::findOrFail($id);
        $application->update([
            'status' => $request->decision,
            'reviewed_by' => $request->user()->id,
        ]);

        $this->audit('review_application', 'Application', $application->id, ['decision' => $request->decision]);

        // Notify applicant via email
        try {
            $applicant = $application->applicant;
            $programName = $application->program?->name ?? 'Unknown';
            Notification::route('mail', $applicant->email)
                ->notify(new ApplicationReviewedNotification($request->decision, $programName));
        } catch (\Exception $e) {
            \Log::error('Application reviewed email failed: ' . $e->getMessage());
        }

        // If accepted, upgrade applicant to STUDENT
        if ($request->decision === 'ACCEPTED') {
            $applicant = $application->applicant;
            $hasConfirmedPayment = Payment::where('student_id', $applicant->id)
                ->where('status', 'CONFIRMED')
                ->exists();

            if ($hasConfirmedPayment) {
                $applicant->update([
                    'role' => 'STUDENT',
                    'status' => 'ACTIVE',
                ]);
            }
        }

        return $this->success($application->load(['applicant', 'program', 'reviewer']));
    }
}
