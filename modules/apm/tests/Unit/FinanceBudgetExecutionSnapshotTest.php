<?php

use App\Services\BudgetExecutionScope;
use App\Services\FinanceBudgetExecutionSnapshot;

test('present for scope filters to allowed divisions and recomputes kpis', function () {
    $snapshot = new FinanceBudgetExecutionSnapshot;
    $org = [
        'budget_year' => 2026,
        'divisions' => [
            [
                'division_id' => 1,
                'division_name' => 'Alpha',
                'division_short_name' => 'A',
                'approved_budget' => 1000,
                'commitment' => 100,
                'actual_expenditure' => 200,
                'budget_balance' => 700,
                'fund_center_count' => 1,
                'fund_centers' => [[
                    'fund_center' => 'FC1',
                    'budget_code' => 'PB-A',
                    'approved_budget' => 1000,
                    'commitment' => 100,
                    'actual_expenditure' => 200,
                    'budget_balance' => 700,
                ]],
            ],
            [
                'division_id' => 2,
                'division_name' => 'Beta',
                'division_short_name' => 'B',
                'approved_budget' => 500,
                'commitment' => 50,
                'actual_expenditure' => 50,
                'budget_balance' => 400,
                'fund_center_count' => 1,
                'fund_centers' => [[
                    'fund_center' => 'FC2',
                    'budget_code' => 'PB-B',
                    'approved_budget' => 500,
                    'commitment' => 50,
                    'actual_expenditure' => 50,
                    'budget_balance' => 400,
                ]],
            ],
        ],
    ];

    $presented = $snapshot->presentForScope($org, [
        'access' => BudgetExecutionScope::ACCESS_DIVISION,
        'allowed_division_ids' => [1],
    ]);

    expect($presented['can_view_all'])->toBeFalse()
        ->and($presented['scope'])->toBe('division')
        ->and($presented['divisions'])->toHaveCount(1)
        ->and($presented['divisions'][0]['division_id'])->toBe(1)
        ->and($presented['kpis']['approved_budget'])->toBe(1000.0)
        ->and($presented['kpis']['used_budget'])->toBe(300.0)
        ->and($presented['show_org_charts'])->toBeFalse()
        ->and($presented['show_budget_code_charts'])->toBeTrue();
});

test('present for scope keeps org-wide when access is all', function () {
    $snapshot = new FinanceBudgetExecutionSnapshot;
    $org = [
        'budget_year' => 2026,
        'divisions' => [
            [
                'division_id' => 1,
                'division_name' => 'Alpha',
                'division_short_name' => 'A',
                'approved_budget' => 100,
                'commitment' => 10,
                'actual_expenditure' => 10,
                'budget_balance' => 80,
                'fund_center_count' => 0,
                'fund_centers' => [],
            ],
            [
                'division_id' => 2,
                'division_name' => 'Beta',
                'division_short_name' => 'B',
                'approved_budget' => 200,
                'commitment' => 20,
                'actual_expenditure' => 20,
                'budget_balance' => 160,
                'fund_center_count' => 0,
                'fund_centers' => [],
            ],
        ],
    ];

    $presented = $snapshot->presentForScope($org, [
        'access' => BudgetExecutionScope::ACCESS_ALL,
        'allowed_division_ids' => null,
    ]);

    expect($presented['can_view_all'])->toBeTrue()
        ->and($presented['show_org_charts'])->toBeTrue()
        ->and($presented['divisions'])->toHaveCount(2)
        ->and($presented['kpis']['approved_budget'])->toBe(300.0);
});
