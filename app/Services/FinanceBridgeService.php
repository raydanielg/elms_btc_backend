<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Application;
use App\Models\MCohort;
use App\Models\MCohortMember;
use App\Services\EnrolmentService;
use Illuminate\Support\Facades\Log;

class FinanceBridgeService
{
    public function __construct(
        private EnrolmentService $enrolmentService
    ) {}

    /**
     * Confirm a payment and trigger the cascade (spec §22.3):
     * 1. Mark payment as CONFIRMED
     * 2. Activate suspended enrolments
     * 3. Upgrade APPLICANT → STUDENT if registration fee
     * 4. If application exists, convert to enrolment
     */
    public function confirmPayment(string $paymentId, string $verifiedBy, ?string $gatewayReference = null): Payment
    {
        $payment = Payment::findOrFail($paymentId);

        $payment->update([
            'status' => 'CONFIRMED',
            'paid_at' => now(),
            'verified_by' => $verifiedBy,
            'gateway_reference' => $gatewayReference,
        ]);

        // Trigger enrolment activation
        $this->enrolmentService->onPaymentConfirmed($payment->student_id, $payment->fee_item);

        // Check if there's an application to convert
        $this->checkApplicationConversion($payment);

        Log::info("Payment confirmed", [
            'payment_id' => $payment->id,
            'student_id' => $payment->student_id,
            'fee_item' => $payment->fee_item,
        ]);

        return $payment->fresh();
    }

    /**
     * Check if a confirmed payment should trigger application conversion (spec §22.3).
     */
    private function checkApplicationConversion(Payment $payment): void
    {
        // If this is a Registration or Admission fee, check for applications
        if (!in_array($payment->fee_item, ['Registration', 'Admission'])) return;

        $application = Application::where('applicant_id', $payment->student_id)
            ->where('status', 'ACCEPTED')
            ->first();

        if (!$application) return;

        // Application is accepted and fee is paid — convert to enrolment
        $this->convertApplicationToEnrolment($application);
    }

    /**
     * Convert an accepted application into enrolment + cohort membership (spec §22.3).
     */
    public function convertApplicationToEnrolment(Application $application): void
    {
        if ($application->status !== 'ACCEPTED') return;

        $user = $application->applicant;

        // Upgrade role to STUDENT if not already
        if ($user->role === 'APPLICANT') {
            $user->update(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        }

        // Add to cohort if specified
        if ($application->cohort_id) {
            MCohortMember::firstOrCreate([
                'cohort_id' => $application->cohort_id,
                'user_id' => $user->id,
            ]);

            // Sync cohort to program courses
            $this->enrolmentService->cohortSync(
                $application->cohort_id,
                $application->program_id,
                null
            );
        }

        // Mark application as converted
        $application->update(['accepted_at' => now()]);

        Log::info("Application converted to enrolment", [
            'application_id' => $application->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Accept an application and create cohort (spec §22.3).
     */
    public function acceptApplication(string $applicationId, string $reviewedBy, ?string $cohortName = null): Application
    {
        $application = Application::findOrFail($applicationId);

        // Create cohort for this intake if not exists
        $cohortName = $cohortName ?: $application->academic_year . ' ' . $application->program->name;
        $cohort = MCohort::firstOrCreate(
            ['name' => $cohortName],
            ['visible' => true, 'description' => 'Auto-created cohort for accepted application']
        );

        $application->update([
            'status' => 'ACCEPTED',
            'reviewed_by' => $reviewedBy,
            'cohort_id' => $cohort->id,
            'accepted_at' => now(),
        ]);

        return $application->fresh();
    }

    /**
     * Reject an application.
     */
    public function rejectApplication(string $applicationId, string $reviewedBy): Application
    {
        $application = Application::findOrFail($applicationId);
        $application->update([
            'status' => 'REJECTED',
            'reviewed_by' => $reviewedBy,
        ]);
        return $application->fresh();
    }
}
