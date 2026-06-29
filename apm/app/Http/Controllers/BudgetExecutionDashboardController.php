<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Models\Division;
use App\Models\Matrix;
use App\Services\BudgetExecutionScope;
use App\Services\BudgetExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            'canViewAllDivisions' => $scope['access'] === BudgetExecutionScope::ACCESS_ALL,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            [$divisionIds, $year, $quarter, $periodMode, $scope] = $this->resolveFilters($request);
            $cacheKeyParts = $this->apmCacheKeyFromRequest($request, ['year', 'quarter', 'period_mode', 'division_id'], [
                '_access' => $scope['access'],
                '_allowed_divisions' => implode(',', $scope['allowed_division_ids'] ?? []),
            ]);

            return $this->apmCachedJson('budget_execution', $request, $cacheKeyParts, function () use (
                $divisionIds,
                $year,
                $quarter,
                $periodMode,
                $scope
            ) {
                $payload = $this->buildPayload($divisionIds, $year, $quarter, $periodMode, $scope);

                return response()->json($payload);
            });
        } catch (Throwable $e) {
            return $this->jsonError($e, $request);
        }
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        [$divisionIds, $year, $quarter, $periodMode, $scope] = $this->resolveFilters($request);
        $payload = $this->buildPayload($divisionIds, $year, $quarter, $periodMode, $scope);
        $periodLabel = $this->periodLabel($year, $quarter, $periodMode);
        $filename = 'budget_execution_' . preg_replace('/\s+/', '_', strtolower($periodLabel)) . '_' . date('Y-m-d_His') . '.csv';

        return response()->stream(function () use ($payload, $periodLabel) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['APM Budget execution — ' . $periodLabel]);
            fputcsv($file, []);
            fputcsv($file, [
                'Division', 'Type', 'Document', 'Title', 'Period',
                'Initiative budget', 'Executed', 'Execution %', 'Status',
                'Fund code', 'Fund activity', 'Code planned', 'Code executed', 'Code remaining', 'Fund working balance',
            ]);

            foreach ($payload['divisions'] ?? [] as $division) {
                foreach ($division['initiatives'] ?? [] as $initiative) {
                    $status = $initiative['fully_executed'] ?? false
                        ? '100% executed'
                        : (($initiative['has_sr_or_arf'] ?? false) ? 'Partial' : 'Not started');
                    $period = trim(($initiative['quarter'] ?? '') . ' ' . ($initiative['year'] ?? ''));
                    $fundCodes = $initiative['fund_codes'] ?? [];

                    if ($fundCodes === []) {
                        fputcsv($file, [
                            $division['division_name'] ?? '',
                            $this->typeLabel($initiative['source_type'] ?? ''),
                            $initiative['document_number'] ?? '',
                            $initiative['title'] ?? '',
                            $period,
                            $initiative['planned_budget'] ?? 0,
                            $initiative['executed_budget'] ?? 0,
                            ($initiative['execution_pct'] ?? 0) . '%',
                            $status,
                            '', '', '', '', '', '',
                        ]);
                        continue;
                    }

                    foreach ($fundCodes as $index => $fc) {
                        fputcsv($file, [
                            $index === 0 ? ($division['division_name'] ?? '') : '',
                            $index === 0 ? $this->typeLabel($initiative['source_type'] ?? '') : '',
                            $index === 0 ? ($initiative['document_number'] ?? '') : '',
                            $index === 0 ? ($initiative['title'] ?? '') : '',
                            $index === 0 ? $period : '',
                            $index === 0 ? ($initiative['planned_budget'] ?? 0) : '',
                            $index === 0 ? ($initiative['executed_budget'] ?? 0) : '',
                            $index === 0 ? (($initiative['execution_pct'] ?? 0) . '%') : '',
                            $index === 0 ? $status : '',
                            $fc['code'] ?? '',
                            $fc['activity'] ?? '',
                            $fc['planned'] ?? 0,
                            $fc['executed'] ?? 0,
                            $fc['remaining'] ?? 0,
                            $fc['working_balance'] ?? 0,
                        ]);
                    }
                }
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        [$divisionIds, $year, $quarter, $periodMode, $scope] = $this->resolveFilters($request);
        $payload = $this->buildPayload($divisionIds, $year, $quarter, $periodMode, $scope);
        $periodLabel = $this->periodLabel($year, $quarter, $periodMode);

        $divisionFilter = '';
        if ($request->filled('division_id')) {
            $div = Division::query()->find((int) $request->input('division_id'));
            $divisionFilter = $div ? $div->division_name : '';
        } elseif ($scope['access'] === BudgetExecutionScope::ACCESS_ALL) {
            $divisionFilter = 'All divisions';
        }

        $htmlData = [
            'payload' => $payload,
            'period_label' => $periodLabel,
            'division_filter' => $divisionFilter,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];

        $mpdf = generate_pdf('budget-execution.export-pdf', $htmlData, ['orientation' => 'L']);
        $filename = 'budget_execution_' . date('Y-m-d_His') . '.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array{0: list<int>|null, 1: int, 2: string|null, 3: string, 4: array<string, mixed>}
     */
    private function resolveFilters(Request $request): array
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

        return [$divisionIds, $year, $quarter, $periodMode, $scope];
    }

    /**
     * @param  list<int>|null  $divisionIds
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function buildPayload(?array $divisionIds, int $year, ?string $quarter, string $periodMode, array $scope): array
    {
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
        $payload['cached_at'] = now()->toIso8601String();

        return $payload;
    }

    private function periodLabel(int $year, ?string $quarter, string $periodMode): string
    {
        return $periodMode === 'annual'
            ? 'Annual ' . $year
            : trim(($quarter ?? '') . ' ' . $year);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'matrix_activity' => 'Matrix activity',
            'single_memo' => 'Single memo',
            'special_memo' => 'Special memo',
            'non_travel_memo' => 'Non-travel',
            default => $type,
        };
    }

    private function jsonError(Throwable $e, Request $request): JsonResponse
    {
        Log::error('Budget execution dashboard failed', [
            'message' => $e->getMessage(),
            'query' => $request->query(),
        ]);

        return response()->json([
            'error' => 'Could not load budget execution data.',
            'message' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
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
