<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Services\PayrollSettingsService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollSettingsController extends Controller
{
    public function show(PayrollSettingsService $settings): JsonResponse
    {
        PayrollAccess::authorizeModule();

        return response()->json(['data' => $settings->current()]);
    }

    public function update(Request $request, PayrollSettingsService $settings): JsonResponse
    {
        PayrollAccess::authorizeSetup();

        $data = $request->validate([
            'default_currency' => 'required|string|size:3',
            'enabled_currencies' => 'required|array|min:1',
            'enabled_currencies.*' => 'string|size:3',
            'period_close_day' => 'required|integer|min:1|max:28',
            'jurisdiction_default' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'message' => 'Payroll settings saved.',
            'data' => $settings->update($data),
        ]);
    }
}
