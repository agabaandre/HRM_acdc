<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollStaffWageItem;
use Modules\Payroll\Services\StaffPayService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollStaffPayController extends Controller
{
    public function directory(StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();

        return response()->json(['data' => $service->directory()]);
    }

    public function show(int $staffId, StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();

        $bundle = $service->bundle($staffId);
        if (! $bundle['staff']) {
            abort(404, 'Staff not found.');
        }

        return response()->json([
            'data' => $bundle,
        ]);
    }

    public function upsert(Request $request, int $staffId, StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();
        $data = $request->validate([
            'currency' => 'required|string|size:3',
            'basic_salary' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string|max:120',
            'bank_account' => 'nullable|string|max:80',
            'bank_branch' => 'nullable|string|max:120',
            'tax_identifier' => 'nullable|string|max:80',
            'pay_status' => 'nullable|in:active,held,terminated',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'message' => 'Staff pay saved.',
            'data' => $service->upsert($staffId, $data),
        ]);
    }

    public function storeWageItem(Request $request, int $staffId, StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();
        $data = $request->validate([
            'wage_type_id' => 'required|integer',
            'amount' => 'nullable|numeric',
            'percent' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        return response()->json([
            'message' => 'Wage item created.',
            'data' => $service->createWageItem($staffId, $data),
        ], 201);
    }

    public function updateWageItem(Request $request, int $staffId, int $id, StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();
        $item = PayrollStaffWageItem::query()->where('staff_id', $staffId)->findOrFail($id);
        $data = $request->validate([
            'wage_type_id' => 'sometimes|integer',
            'amount' => 'nullable|numeric',
            'percent' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        return response()->json([
            'message' => 'Wage item updated.',
            'data' => $service->updateWageItem($item, $data),
        ]);
    }

    public function destroyWageItem(int $staffId, int $id, StaffPayService $service): JsonResponse
    {
        PayrollAccess::authorizeStaffPay();
        $item = PayrollStaffWageItem::query()->where('staff_id', $staffId)->findOrFail($id);
        $service->deleteWageItem($item);

        return response()->json(['message' => 'Wage item deleted.']);
    }
}
