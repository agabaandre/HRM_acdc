<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollLoan;
use Modules\Payroll\Models\PayrollLoanSchedule;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollLoanController extends Controller
{
    public function index(Request $request, LoanService $service): JsonResponse
    {
        PayrollAccess::authorizeModule();

        $filters = [];
        if ($request->boolean('mine') || (! PayrollAccess::canManageLoans() && ! PayrollAccess::canApproveLoans())) {
            $filters['mine'] = true;
        }
        if ($request->boolean('pending_approval')) {
            PayrollAccess::authorizeApproveLoans();
            $filters['pending_approval'] = true;
        }
        if ($request->filled('status') && PayrollAccess::canManageLoans()) {
            $filters['status'] = $request->query('status');
        }
        if ($request->filled('staff_id') && PayrollAccess::canManageLoans()) {
            $filters['staff_id'] = (int) $request->query('staff_id');
        }

        return response()->json(['data' => $service->list($filters)]);
    }

    public function store(Request $request, LoanService $service): JsonResponse
    {
        if (! PayrollAccess::canRequestLoan()) {
            abort(403, 'You do not have permission to request a loan.');
        }

        $data = $request->validate([
            'staff_id' => 'nullable|integer',
            'type' => 'required|in:loan,advance',
            'currency' => 'nullable|string|size:3',
            'principal' => 'required|numeric|gt:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'installment_amount' => 'nullable|numeric|gt:0',
            'installment_count' => 'nullable|integer|min:1',
            'supervisor_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'message' => 'Loan request submitted.',
            'data' => $service->request($data, auth()->id() ? (int) auth()->id() : null),
        ], 201);
    }

    public function decide(Request $request, int $id, LoanService $service): JsonResponse
    {
        PayrollAccess::authorizeApproveLoans();
        $loan = PayrollLoan::query()->findOrFail($id);
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'message' => 'Loan decision recorded.',
            'data' => $service->decide($loan, $data, auth()->id() ? (int) auth()->id() : null),
        ]);
    }

    public function disburse(Request $request, int $id, LoanService $service): JsonResponse
    {
        PayrollAccess::authorizeManageLoans();
        $loan = PayrollLoan::query()->findOrFail($id);
        $data = $request->validate([
            'start_period_id' => 'required|integer',
            'installment_amount' => 'nullable|numeric|gt:0',
            'installment_count' => 'nullable|integer|min:1',
        ]);

        return response()->json([
            'message' => 'Loan disbursed.',
            'data' => $service->disburse($loan, $data),
        ]);
    }

    public function waive(int $id, int $scheduleId, LoanService $service): JsonResponse
    {
        PayrollAccess::authorizeManageLoans();
        $schedule = PayrollLoanSchedule::query()
            ->where('loan_id', $id)
            ->whereKey($scheduleId)
            ->firstOrFail();

        return response()->json([
            'message' => 'Installment waived.',
            'data' => $service->waiveSchedule($schedule),
        ]);
    }
}
