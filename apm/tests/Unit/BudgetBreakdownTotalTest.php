<?php

use App\Support\BudgetBreakdownTotal;

it('sums fund-code line items and ignores stale grand_total', function () {
    $breakdown = [
        '12' => [
            ['unit_cost' => 100, 'units' => 2, 'days' => 1],
            ['unit_cost' => 50.5, 'units' => 1, 'days' => 2],
        ],
        'grand_total' => 99999.99,
    ];

    expect(BudgetBreakdownTotal::fromFundCodeBreakdown($breakdown))->toBe(301.0);
});

it('falls back to grand_total when no line items', function () {
    $breakdown = ['grand_total' => 80842.0];

    expect(BudgetBreakdownTotal::fromFundCodeBreakdown($breakdown))->toBe(80842.0);
});

it('sums non-travel memo by quantity times unit cost', function () {
    $breakdown = [
        '5' => [
            ['quantity' => 3, 'unit_cost' => 10],
            ['quantity' => 2, 'unit_cost' => 25.5],
        ],
        'grand_total' => 1,
    ];

    expect(BudgetBreakdownTotal::fromNonTravelBreakdown($breakdown))->toBe(81.0);
});
