<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinGrant;
use App\Services\FinanceAfefCategoryService;
use App\Services\FinanceApmOrgService;
use App\Services\FinanceGeographyService;
use App\Services\PortfolioIntramuralService;
use App\Services\SapTrendService;
use App\Support\FinancePermissions;
use App\Support\FinanceYearConfig;
use App\Support\PortfolioSectionSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortfolioController extends Controller
{
    public const SECTIONS = PortfolioSectionSchema::EDITABLE;

    public function __construct(
        private readonly PortfolioIntramuralService $intramural = new PortfolioIntramuralService,
        private readonly FinanceApmOrgService $apmOrg = new FinanceApmOrgService,
        private readonly FinanceGeographyService $geography = new FinanceGeographyService,
        private readonly FinanceAfefCategoryService $afefCategories = new FinanceAfefCategoryService,
        private readonly SapTrendService $trends = new SapTrendService,
        private readonly FinanceYearConfig $fy = new FinanceYearConfig,
    ) {}

    public function summary(): JsonResponse
    {
        $year = $this->fy->currentFyYear();
        $intramural = $this->intramural->forYear($year, [
            'include_zero_divisions' => true,
            'include_apm_divisions' => true,
            'fund_center_preview' => 40,
            'cache' => true,
        ]);

        $sections = [];
        foreach (self::SECTIONS as $section) {
            $rows = $this->sectionRows($section, $year);
            $sections[$section] = [
                'row_count' => count($rows),
                'rows' => $rows,
            ];
        }

        $trendPayload = $this->trends->trends($year);

        return response()->json([
            'success' => true,
            'data' => [
                'budget_year' => $year,
                'financial_year' => $trendPayload['financial_year'],
                'intramural' => $intramural,
                'sections' => $sections,
                'kpis' => $this->portfolioKpis($intramural, $sections),
                'performance' => $this->performanceRanking($intramural),
                'trends' => [
                    'monthly' => $trendPayload['monthly'],
                    'quarterly' => $trendPayload['quarterly'],
                ],
                'schemas' => PortfolioSectionSchema::catalog(),
                'sap_import' => $intramural['sap_import'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $intramural
     * @param  array<string, array{row_count: int, rows: list<array<string, mixed>>}>  $sections
     * @return array<string, mixed>
     */
    private function portfolioKpis(array $intramural, array $sections): array
    {
        $extraRows = $sections['extramural']['rows'] ?? [];
        $extraApproved = 0.0;
        $extraReleased = 0.0;
        $extraActual = 0.0;
        $extraCommitment = 0.0;
        foreach ($extraRows as $row) {
            $d = is_array($row['data'] ?? null) ? $row['data'] : [];
            $extraApproved += (float) ($d['approved_budget'] ?? 0);
            $extraReleased += (float) ($d['released_budget'] ?? 0);
            $extraActual += (float) ($d['actual_expenditure'] ?? 0);
            $extraCommitment += (float) ($d['commitment'] ?? 0);
        }
        $extraBase = $extraReleased > 0 ? $extraReleased : $extraApproved;

        $indirectRows = $sections['indirect_cost']['rows'] ?? [];
        $indirectEarned = 0.0;
        $indirectAvailable = 0.0;
        foreach ($indirectRows as $row) {
            $d = is_array($row['data'] ?? null) ? $row['data'] : [];
            $indirectEarned += (float) ($d['acdc_indirect_cost'] ?? $d['indirect_to_earn'] ?? 0);
            $indirectAvailable += (float) ($d['indirect_available'] ?? 0);
        }

        $adminRows = $sections['administrative_cost']['rows'] ?? [];
        $adminAmount = 0.0;
        foreach ($adminRows as $row) {
            $d = is_array($row['data'] ?? null) ? $row['data'] : [];
            $adminAmount += (float) ($d['amount'] ?? 0);
        }

        $investRows = $sections['investments']['rows'] ?? [];
        $bankBalance = 0.0;
        $invested = 0.0;
        foreach ($investRows as $row) {
            $d = is_array($row['data'] ?? null) ? $row['data'] : [];
            $bankBalance += (float) ($d['bank_balance_usd'] ?? 0);
            $invested += (float) ($d['invested_amount'] ?? 0);
        }

        $afefRows = $sections['afef']['rows'] ?? [];
        $afefBalance = 0.0;
        $afefByCategory = [];
        foreach ($afefRows as $row) {
            $d = is_array($row['data'] ?? null) ? $row['data'] : [];
            $amount = (float) ($d['amount'] ?? 0);
            $cat = trim((string) ($d['category'] ?? 'other')) ?: 'other';
            if (strcasecmp((string) ($d['label'] ?? ''), 'Total AfEF Fund Balance') === 0
                || $cat === 'balance') {
                $afefBalance = $amount;
            }
            $afefByCategory[$cat] = ($afefByCategory[$cat] ?? 0) + $amount;
        }
        if ($afefBalance == 0.0 && $afefRows !== []) {
            $afefBalance = array_sum($afefByCategory);
        }

        return [
            'intramural' => [
                'approved_budget' => (float) ($intramural['approved_budget'] ?? 0),
                'budget_balance' => (float) ($intramural['budget_balance'] ?? 0),
                'commitment' => (float) ($intramural['commitment'] ?? 0),
                'actual_expenditure' => (float) ($intramural['actual_expenditure'] ?? 0),
                'execution_rate' => $intramural['execution_rate'] ?? null,
                'fund_center_count' => (int) ($intramural['fund_center_count'] ?? 0),
            ],
            'extramural' => [
                'approved_budget' => $extraApproved,
                'released_budget' => $extraReleased,
                'commitment' => $extraCommitment,
                'actual_expenditure' => $extraActual,
                'execution_rate' => $extraBase > 0 ? ($extraActual / $extraBase) : null,
                'row_count' => count($extraRows),
            ],
            'indirect_cost' => [
                'earned' => $indirectEarned,
                'available' => $indirectAvailable,
                'row_count' => count($indirectRows),
            ],
            'administrative_cost' => [
                'amount' => $adminAmount,
                'row_count' => count($adminRows),
            ],
            'investments' => [
                'bank_balance_usd' => $bankBalance,
                'invested_amount' => $invested,
                'row_count' => count($investRows),
            ],
            'afef' => [
                'balance' => $afefBalance,
                'by_category' => $afefByCategory,
                'row_count' => count($afefRows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $intramural
     * @return list<array<string, mixed>>
     */
    private function performanceRanking(array $intramural): array
    {
        $rows = [];
        foreach ($intramural['by_division'] ?? [] as $div) {
            if (! is_array($div) || (int) ($div['fund_center_count'] ?? 0) < 1) {
                continue;
            }
            if ((int) ($div['division_id'] ?? 0) === 0) {
                continue;
            }
            $approved = (float) ($div['approved_budget'] ?? 0);
            $actual = (float) ($div['actual_expenditure'] ?? 0);
            $commitment = (float) ($div['commitment'] ?? 0);
            $fundCenters = $div['fund_centers'] ?? [];
            if (is_array($fundCenters)) {
                usort($fundCenters, function (array $a, array $b): int {
                    return ($b['execution_rate'] ?? -1) <=> ($a['execution_rate'] ?? -1);
                });
            } else {
                $fundCenters = [];
            }
            $rows[] = [
                'division_id' => (int) $div['division_id'],
                'division_name' => (string) ($div['division_name'] ?? ''),
                'division_short_name' => (string) ($div['division_short_name'] ?? ''),
                'fund_center_count' => (int) ($div['fund_center_count'] ?? 0),
                'approved_budget' => $approved,
                'budget_balance' => (float) ($div['budget_balance'] ?? 0),
                'commitment' => $commitment,
                'actual_expenditure' => $actual,
                'cy_expenditure' => (float) ($div['cy_expenditure'] ?? ($commitment + $actual)),
                'execution_rate' => $div['execution_rate'] ?? ($approved > 0 ? (($approved - (float) ($div['budget_balance'] ?? 0)) / $approved) : null),
                'fund_centers' => $fundCenters,
            ];
        }
        // Best execution first; null/unknown rates sink to the bottom.
        usort($rows, function (array $a, array $b): int {
            $aRate = $a['execution_rate'];
            $bRate = $b['execution_rate'];
            $aMissing = $aRate === null;
            $bMissing = $bRate === null;
            if ($aMissing !== $bMissing) {
                return $aMissing ? 1 : -1;
            }
            if (! $aMissing && (float) $bRate !== (float) $aRate) {
                return (float) $bRate <=> (float) $aRate;
            }

            return strcasecmp((string) $a['division_name'], (string) $b['division_name']);
        });
        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank'] = $rank++;
        }
        unset($row);

        return $rows;
    }

    public function schemas(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'editable' => self::SECTIONS,
                'schemas' => PortfolioSectionSchema::catalog(),
                'intramural' => [
                    'source' => 'sap_import',
                    'read_only' => true,
                    'columns' => [
                        'division_short_name',
                        'division_name',
                        'main_budget',
                        'approved_budget',
                        'commitment',
                        'actual_expenditure',
                        'budget_balance',
                        'execution_rate',
                        'fund_center_count',
                    ],
                ],
            ],
        ]);
    }

    public function section(Request $request, string $section): JsonResponse
    {
        if ($section === 'intramural') {
            $year = (int) date('Y');
            $intra = $this->intramural->forYear($year);

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => 'intramural',
                    'budget_year' => $year,
                    'read_only' => true,
                    'can_edit' => false,
                    'source' => 'sap_import',
                    'intramural' => $intra,
                    'rows' => $intra['by_division'],
                ],
            ]);
        }

        $this->assertSection($section);
        $year = (int) date('Y');
        $canEdit = FinancePermissions::canManageFundingPortfolio(
            $request->attributes->get('risk_permissions', [])
        );

        $payload = [
            'section' => $section,
            'budget_year' => $year,
            'read_only' => ! $canEdit,
            'can_edit' => $canEdit,
            'schema' => PortfolioSectionSchema::fields($section),
            'empty_row' => PortfolioSectionSchema::emptyRow($section),
            'rows' => $this->sectionRows($section, $year),
        ];
        if (in_array($section, ['extramural', 'indirect_cost'], true)) {
            $payload['funders'] = $this->apmOrg->apmFunders(true);
            $payload['funder_aliases'] = $this->apmOrg->snapshot()['funder_aliases'];
            $payload['grants'] = Schema::hasTable('fin_grants')
                ? FinGrant::query()->where('is_active', true)->orderBy('name')->get([
                    'id', 'name', 'short_name', 'funder_id', 'funder_name', 'end_date',
                    'total_budget', 'direct_cost_usd', 'indirect_cost', 'indirect_pct', 'budget_code',
                ])
                : [];
        }
        if ($section === 'investments') {
            $geo = $this->geography->snapshot();
            $payload['regions'] = $geo['regions'];
            $payload['countries'] = $geo['countries'];
            $payload['locations'] = $geo['locations'];
        }
        if ($section === 'afef') {
            $payload['afef_categories'] = $this->afefCategories->all(true);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function replaceSection(Request $request, string $section): JsonResponse
    {
        if ($section === 'intramural') {
            return response()->json([
                'success' => false,
                'message' => 'Intramural is sourced from the latest SAP import and cannot be edited here.',
            ], 422);
        }

        if (! FinancePermissions::canManageFundingPortfolio(
            $request->attributes->get('risk_permissions', [])
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Managing the Funding Portfolio requires the manage_funding_portfolio permission.',
            ], 403);
        }

        $this->assertSection($section);
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.data' => ['required', 'array'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $year = (int) date('Y');
        $allowed = array_column(PortfolioSectionSchema::fields($section), 'key');

        DB::transaction(function () use ($section, $year, $validated, $allowed) {
            DB::table('fin_portfolio_entries')
                ->where('section', $section)
                ->where('budget_year', $year)
                ->delete();

            $inserts = [];
            foreach (array_values($validated['rows']) as $i => $row) {
                $data = is_array($row['data'] ?? null) ? $row['data'] : [];
                $clean = [];
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $data)) {
                        $clean[$key] = $data[$key];
                    }
                }
                // Keep unknown keys from older rows so we do not wipe custom notes.
                foreach ($data as $k => $v) {
                    if (! array_key_exists($k, $clean)) {
                        $clean[$k] = $v;
                    }
                }
                $inserts[] = [
                    'section' => $section,
                    'budget_year' => $year,
                    'sort_order' => (int) ($row['sort_order'] ?? $i),
                    'data' => json_encode($clean, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($inserts !== []) {
                DB::table('fin_portfolio_entries')->insert($inserts);
            }
        });

        return $this->section($request, $section);
    }

    /**
     * @return list<array{id: int, sort_order: int, data: array<string, mixed>}>
     */
    private function sectionRows(string $section, int $year): array
    {
        return DB::table('fin_portfolio_entries')
            ->where('section', $section)
            ->where('budget_year', $year)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'sort_order' => $r->sort_order,
                'data' => json_decode((string) $r->data, true) ?: [],
            ])
            ->all();
    }

    private function assertSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            abort(404, 'Unknown portfolio section.');
        }
    }
}
