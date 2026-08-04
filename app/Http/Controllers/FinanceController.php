<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Debt;
use App\Models\User;

class FinanceController extends Controller
{
    // ===== Fee Structures =====
    public function getFees()
    {
        $fees = FeeStructure::with('program')->latest()->get();
        return $this->success($fees);
    }

    public function createFee(Request $request)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'mode' => 'required|in:ODL,DAY,BRD',
            'item' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'academic_year' => 'required|string|max:9',
        ]);

        $fee = FeeStructure::create($data);
        return $this->success($fee, 201);
    }

    public function updateFee(Request $request, string $id)
    {
        $fee = FeeStructure::findOrFail($id);

        $data = $request->validate([
            'program_id' => 'sometimes|exists:programs,id',
            'mode' => 'sometimes|in:ODL,DAY,BRD',
            'item' => 'sometimes|string|max:100',
            'amount' => 'sometimes|numeric|min:0',
            'academic_year' => 'sometimes|string|max:9',
        ]);

        $fee->update($data);
        return $this->success($fee);
    }

    // ===== Payments =====
    public function getPayments(Request $request)
    {
        $query = Payment::with('student');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate($request->get('limit', 20));
        return $this->paginated($payments);
    }

    public function verifyPayment(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status === 'CONFIRMED') {
            return $this->error('ALREADY_VERIFIED', 'This payment is already confirmed.', 409);
        }

        $payment->update([
            'status' => 'CONFIRMED',
            'paid_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $this->audit('verify_payment', 'Payment', $payment->id, ['amount' => $payment->amount]);

        // Check if applicant should be upgraded to student
        $student = $payment->student;
        if ($student && $student->role === 'APPLICANT') {
            $acceptedApp = $student->applications()
                ->where('status', 'ACCEPTED')
                ->exists();

            if ($acceptedApp) {
                $student->update([
                    'role' => 'STUDENT',
                    'status' => 'ACTIVE',
                ]);
            }
        }

        return $this->success($payment->load('student'));
    }

    // ===== Debts =====
    public function getDebts()
    {
        $debts = Debt::with('student')->latest()->get();
        return $this->success($debts);
    }

    public function createDebt(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'description' => 'required|string|max:150',
            'amount_due' => 'required|numeric|min:0',
            'due_date' => 'sometimes|date',
        ]);

        $debt = Debt::create([
            'student_id' => $data['student_id'],
            'description' => $data['description'],
            'amount_due' => $data['amount_due'],
            'amount_paid' => 0,
            'balance' => $data['amount_due'],
            'due_date' => $data['due_date'] ?? null,
        ]);

        return $this->success($debt, 201);
    }

    public function updateDebt(Request $request, string $id)
    {
        $debt = Debt::findOrFail($id);

        $data = $request->validate([
            'description' => 'sometimes|string|max:150',
            'amount_due' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'due_date' => 'sometimes|date',
        ]);

        if (isset($data['amount_paid'])) {
            $data['balance'] = ($data['amount_due'] ?? $debt->amount_due) - $data['amount_paid'];
        }

        $debt->update($data);
        return $this->success($debt);
    }

    // ===== Student Self-Service =====
    public function myPayments(Request $request)
    {
        $payments = Payment::where('student_id', $request->user()->id)->latest()->get();
        return $this->success($payments);
    }

    public function myDebts(Request $request)
    {
        $debts = Debt::where('student_id', $request->user()->id)->latest()->get();
        return $this->success($debts);
    }

    public function myResults(Request $request)
    {
        $results = \App\Models\Result::where('student_id', $request->user()->id)
            ->with('course')
            ->latest()
            ->get();

        return $this->success($results);
    }
}
