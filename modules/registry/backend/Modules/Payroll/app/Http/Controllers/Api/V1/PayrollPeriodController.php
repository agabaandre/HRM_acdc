<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Services\PayrollPeriodService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollPeriodController extends Controller
{
    public function index(PayrollPeriodService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();

        return response()->json(['data' => $service->list()]);
    }

    public function store(Request $request, PayrollPeriodService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'label' => 'nullable|string|max:40',
        ]);

        return response()->json([
            'message' => 'Period created.',
            'data' => $service->create($data),
        ], 201);
    }

    public function close(int $id, PayrollPeriodService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $period = PayrollPeriod::query()->findOrFail($id);

        return response()->json([
            'message' => 'Period closed.',
            'data' => $service->close($period),
        ]);
    }

    public function upsertFx(Request $request, int $id, PayrollPeriodService $service): JsonResponse
    {
        PayrollAccess::authorizeRun();
        $period = PayrollPeriod::query()->findOrFail($id);
        $data = $request->validate([
            'rates' => 'required|array|min:1',
            'rates.*.currency' => 'required|string|size:3',
            'rates.*.rate_to_default' => 'required|numeric|gt:0',
        ]);

        return response()->json([
            'message' => 'FX rates saved.',
            'data' => $service->upsertFx($period, $data['rates']),
        ]);
    }
}
