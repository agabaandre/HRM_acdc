<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\FinanceYearConfig;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceYearSettingsController extends Controller
{
    public function __construct(
        private readonly FinanceYearConfig $fy = new FinanceYearConfig,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->fy->snapshot(), [
                'current_fy_year' => $this->fy->currentFyYear(),
                'can_edit' => true,
                'months' => $this->monthOptions(),
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->fy->setStartMonth((int) $validated['start_month']);

        return response()->json([
            'success' => true,
            'message' => 'Financial year start month saved.',
            'data' => array_merge($this->fy->snapshot(), [
                'current_fy_year' => $this->fy->currentFyYear(),
            ]),
        ]);
    }

    /**
     * @return list<array{value: int, title: string}>
     */
    private function monthOptions(): array
    {
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = [
                'value' => $m,
                'title' => $this->fy->monthName($m),
            ];
        }

        return $out;
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
