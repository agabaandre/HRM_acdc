<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Models\Division;
use App\Models\Matrix;
use App\Services\BudgetExecutionScope;
use App\Services\BudgetExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetExecutionDashboardController extends Controller
{
    use CachesApmPageResponses;

    public function index(): View
    {
        $scope = BudgetExecutionScope::resolve();
        $divisions = $this->divisionsForScope($scope);
        $currentYear = (int) date('Y');
        $years = Matrix::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->filter(fn ($y) => $y > 0)
            ->values()
            ->all();

        if (! in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $currentQuarter = 'Q' . (int) ceil((int) date('n') / 3);

        return view('budget-execution.index', [
            'scope' => $scope,
            'divisions' => $divisions,
            'years' => $years,
            'quarters' => $quarters,
            'currentYear' => $currentYear,
            'currentQuarter' => $currentQuarter,
            'canPickDivision' => ($scope['allowed_division_ids'] ?? null) === null
                || count($scope['allowed_division_ids'] ?? []) > 1,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
            'period_mode' => 'nullable|string|in:quarterly,annual',
            'division_id' => 'nullable|integer|exists:divisions,id',
        ]);

        $scope = BudgetExecutionScope::resolve();
        $divisionId = isset($validated['division_id']) ? (int) $validated['division_id'] : null;
        BudgetExecutionScope::assertDivisionAllowed($scope, $divisionId);

        $divisionIds = null;
        if ($divisionId !== null && $divisionId > 0) {
            $divisionIds = [$divisionId];
        } elseif (($scope['allowed_division_ids'] ?? null) !== null) {
            $divisionIds = $scope['allowed_division_ids'];
        }

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) date('Y');
        $quarter = $validated['quarter'] ?? null;
        $periodMode = $validated['period_mode'] ?? 'quarterly';

        if ($periodMode === 'annual') {
            $quarter = null;
        }

        $cacheKey = $this->apmCacheKeyFromRequest($request, ['year', 'quarter', 'period_mode', 'division_id']);
        if ($cached = \App\Services\ApmPageCache::get('reports', $cacheKey)) {
            return response()->json(is_array($cached) ? $cached : []);
        }

        $payload = app(BudgetExecutionService::class)->buildDashboard(
            $divisionIds,
            $year,
            $quarter,
            $periodMode
        );

        $payload['scope'] = [
            'access' => $scope['access'],
            'is_director' => $scope['is_director'],
        ];

        \App\Services\ApmPageCache::put('reports', $cacheKey, $payload, 120);

        return response()->json($payload);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function divisionsForScope(array $scope)
    {
        $query = Division::query()->orderBy('division_name');
        $allowed = $scope['allowed_division_ids'] ?? null;
        if ($allowed !== null) {
            if ($allowed === []) {
                return collect();
            }
            $query->whereIn('id', $allowed);
        }

        return $query->get(['id', 'division_name']);
    }
}
