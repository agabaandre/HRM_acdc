<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollWageType;
use Modules\Payroll\Services\WageTypeService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollWageTypeController extends Controller
{
    public function index(WageTypeService $service): JsonResponse
    {
        PayrollAccess::authorizeModule();

        return response()->json(['data' => $service->list()]);
    }

    public function store(Request $request, WageTypeService $service): JsonResponse
    {
        PayrollAccess::authorizeSetup();
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:120',
            'category' => 'required|string',
            'calc_method' => 'required|string',
            'percent_base' => 'nullable|string|max:40',
            'default_amount' => 'nullable|numeric',
            'taxable' => 'boolean',
            'pre_tax' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        return response()->json([
            'message' => 'Wage type created.',
            'data' => $service->create($data),
        ], 201);
    }

    public function update(Request $request, int $id, WageTypeService $service): JsonResponse
    {
        PayrollAccess::authorizeSetup();
        $type = PayrollWageType::query()->findOrFail($id);
        $data = $request->validate([
            'code' => 'sometimes|string|max:40',
            'name' => 'sometimes|string|max:120',
            'category' => 'sometimes|string',
            'calc_method' => 'sometimes|string',
            'percent_base' => 'nullable|string|max:40',
            'default_amount' => 'nullable|numeric',
            'taxable' => 'boolean',
            'pre_tax' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        return response()->json([
            'message' => 'Wage type updated.',
            'data' => $service->update($type, $data),
        ]);
    }

    public function destroy(int $id, WageTypeService $service): JsonResponse
    {
        PayrollAccess::authorizeSetup();
        $type = PayrollWageType::query()->findOrFail($id);

        return response()->json([
            'message' => 'Wage type deactivated.',
            'data' => $service->deactivate($type),
        ]);
    }
}
