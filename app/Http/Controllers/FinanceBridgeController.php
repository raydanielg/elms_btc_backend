<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Application;
use App\Models\MPaymentGateway;
use App\Services\FinanceBridgeService;

class FinanceBridgeController extends Controller
{
    public function __construct(
        private FinanceBridgeService $bridgeService
    ) {}

    /** POST /payments/:id/verify — confirm a payment (spec §22.3) */
    public function verifyPayment(Request $request, string $paymentId)
    {
        $data = $request->validate([
            'gateway_reference' => 'nullable|string|max:100',
        ]);

        $payment = $this->bridgeService->confirmPayment(
            $paymentId,
            $request->user()->id,
            $data['gateway_reference'] ?? null
        );

        return $this->success($payment);
    }

    /** GET /payment-gateways — list active gateways */
    public function indexGateways()
    {
        return $this->success(MPaymentGateway::where('active', true)->orderBy('sortorder')->get());
    }

    /** POST /payment-gateways — create a gateway config (SUPER_ADMIN) */
    public function createGateway(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:gepg,manual,stripe,mpesa,selcom',
            'config' => 'sometimes|array',
            'sortorder' => 'sometimes|integer',
        ]);

        $gateway = MPaymentGateway::create($data);
        return $this->success($gateway, 201);
    }

    /** POST /applications/:id/accept — accept application (spec §22.3) */
    public function acceptApplication(Request $request, string $applicationId)
    {
        $data = $request->validate([
            'cohort_name' => 'nullable|string|max:150',
        ]);

        $application = $this->bridgeService->acceptApplication(
            $applicationId,
            $request->user()->id,
            $data['cohort_name'] ?? null
        );

        return $this->success($application);
    }

    /** POST /applications/:id/reject — reject application */
    public function rejectApplication(Request $request, string $applicationId)
    {
        $application = $this->bridgeService->rejectApplication(
            $applicationId,
            $request->user()->id
        );

        return $this->success($application);
    }

    /** GET /applications — list applications with filters */
    public function indexApplications(Request $request)
    {
        $query = Application::with(['applicant', 'program']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($programId = $request->get('program_id')) {
            $query->where('program_id', $programId);
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate(20);
        return $this->paginated($applications);
    }

    /** GET /applications/:id — view application detail */
    public function showApplication(string $id)
    {
        $application = Application::with(['applicant', 'program', 'cohort'])->findOrFail($id);
        return $this->success($application);
    }
}
