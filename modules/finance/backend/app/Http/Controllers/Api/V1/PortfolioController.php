<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    public const SECTIONS = [
        'extramural',
        'indirect_cost',
        'administrative_cost',
        'investments',
        'afef',
    ];

    public function summary(): JsonResponse
    {
        $year = (int) date('Y');
        $latestImport = DB::table('fin_sap_imports')
            ->where('budget_year', $year)
            ->where('is_latest', true)
            ->orderByDesc('id')
            ->first();

        $intramural = [
            'approved_budget' => 0.0,
            'budget_balance' => 0.0,
            'fund_center_count' => 0,
            'execution_rate' => null,
            'by_fund_center' => [],
        ];

        if ($latestImport) {
            $centers = DB::table('fin_sap_fund_centers')
                ->where('import_id', $latestImport->id)
                ->orderBy('fund_center')
                ->get();
            $approved = 0.0;
            $balance = 0.0;
            $byFc = [];
            foreach ($centers as $c) {
                $a = (float) $c->total_released_budget;
                $b = (float) $c->released_budget_balance;
                $approved += $a;
                $balance += $b;
                $byFc[] = [
                    'fund_center' => $c->fund_center,
                    'approved_budget' => $a,
                    'budget_balance' => $b,
                    'execution_rate' => $a > 0 ? (($a - $b) / $a) : null,
                ];
            }
            $intramural = [
                'approved_budget' => $approved,
                'budget_balance' => $balance,
                'fund_center_count' => count($byFc),
                'execution_rate' => $approved > 0 ? (($approved - $balance) / $approved) : null,
                'by_fund_center' => array_slice($byFc, 0, 30),
            ];
        }

        $sections = [];
        foreach (self::SECTIONS as $section) {
            $rows = DB::table('fin_portfolio_entries')
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
            $sections[$section] = [
                'row_count' => count($rows),
                'rows' => $rows,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'budget_year' => $year,
                'intramural' => $intramural,
                'sections' => $sections,
                'sap_import' => $latestImport,
            ],
        ]);
    }

    public function section(string $section): JsonResponse
    {
        $this->assertSection($section);
        $year = (int) date('Y');
        $rows = DB::table('fin_portfolio_entries')
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

        return response()->json([
            'success' => true,
            'data' => [
                'section' => $section,
                'budget_year' => $year,
                'rows' => $rows,
            ],
        ]);
    }

    public function replaceSection(Request $request, string $section): JsonResponse
    {
        $this->assertSection($section);
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.data' => ['required', 'array'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $year = (int) date('Y');
        DB::transaction(function () use ($section, $year, $validated) {
            DB::table('fin_portfolio_entries')
                ->where('section', $section)
                ->where('budget_year', $year)
                ->delete();

            $inserts = [];
            foreach (array_values($validated['rows']) as $i => $row) {
                $inserts[] = [
                    'section' => $section,
                    'budget_year' => $year,
                    'sort_order' => (int) ($row['sort_order'] ?? $i),
                    'data' => json_encode($row['data'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($inserts !== []) {
                DB::table('fin_portfolio_entries')->insert($inserts);
            }
        });

        return $this->section($section);
    }

    private function assertSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            abort(404, 'Unknown portfolio section.');
        }
    }
}
