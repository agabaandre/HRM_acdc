<?php

namespace Modules\Payroll\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Payroll\Services\PayrollDashboardService;
use Modules\Payroll\Support\PayrollAccess;

class PayrollDashboardController extends Controller
{
    public function __invoke(PayrollDashboardService $dashboard): JsonResponse
    {
        PayrollAccess::authorizeModule();

        return response()->json(['data' => $dashboard->summary()]);
    }
}
