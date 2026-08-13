<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollTaxRule;
use Modules\Payroll\Services\TaxRuleService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollTaxRuleController extends Controller
{
    public function index(TaxRuleService $service): JsonResponse
    {
        PayrollAccess::authorizeModule();

        return response()->json(['data' => $service->list()]);
    }

    public function store(Request $request, TaxRuleService $service): JsonResponse
    {
        PayrollAccess::authorizeSetup();
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:120',
            'jurisdiction_code' => 'nullable|string|max:64',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date',
            'applies_to' => 'required|in:employee,employer',
            'wage_type_id' => 'nullable|integer',
            'is_active' => 'boolean',
            'bands' => 'array',
            'bands.*.from_amount' => 'required_with:bands|numeric|min:0',
            'bands.*.to_amount' => 'nullable|numeric',
            'bands.*.rate_percent' => 'numeric|min:0',
            'bands.*.fixed_amount' => 'numeric|min:0',
        ]);

        return response()->json([
            'message' => 'Tax rule created.',
            'data' => $service->create($data),
        ], 201);
    }

    public function update(Request $request, int $id, TaxRuleService $service): JsonResponse
    {
        PayrollAccess::authorizeSetup();
        $rule = PayrollTaxRule::query()->findOrFail($id);
        $data = $request->validate([
            'code' => 'sometimes|string|max:40',
            'name' => 'sometimes|string|max:120',
            'jurisdiction_code' => 'nullable|string|max:64',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date',
            'applies_to' => 'sometimes|in:employee,employer',
            'wage_type_id' => 'nullable|integer',
            'is_active' => 'boolean',
            'bands' => 'array',
            'bands.*.from_amount' => 'required_with:bands|numeric|min:0',
            'bands.*.to_amount' => 'nullable|numeric',
            'bands.*.rate_percent' => 'numeric|min:0',
            'bands.*.fixed_amount' => 'numeric|min:0',
        ]);

        return response()->json([
            'message' => 'Tax rule updated.',
            'data' => $service->update($rule, $data),
        ]);
    }
}
