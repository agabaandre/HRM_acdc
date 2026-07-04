<?php

use App\Support\BudgetBreakdownTotal;

it('returns per-fund-code total with days multiplier', function () {
    $breakdown = [
        '277' => [
            ['unit_cost' => 100, 'units' => 2, 'days' => 3],
        ],
        '99' => [
            ['unit_cost' => 50, 'units' => 1, 'days' => 1],
        ],
    ];

    expect(BudgetBreakdownTotal::forFundCode($breakdown, 277, BudgetBreakdownTotal::STYLE_TRAVEL_STRICT))->toBe(600.0)
        ->and(BudgetBreakdownTotal::memoGrandTotal($breakdown, BudgetBreakdownTotal::STYLE_TRAVEL_STRICT))->toBe(650.0);
});

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

it('prefers budget breakdown over stale activity budget sum', function () {
    $breakdown = [
        '277' => [
            ['unit_cost' => 1000, 'units' => 86, 'days' => 1],
        ],
    ];

    expect(BudgetBreakdownTotal::activityAmountForFundCode($breakdown, 277, 78028.0))->toBe(86000.0);
});

it('matches travel strict view requiring unit_cost and units keys', function () {
    $breakdown = [
        '277' => [
            ['unit_cost' => 100, 'units' => 2, 'days' => 1],
            ['unit_cost' => 50, 'quantity' => 4],
        ],
    ];

    expect(BudgetBreakdownTotal::forFundCode($breakdown, 277, BudgetBreakdownTotal::STYLE_TRAVEL_STRICT))->toBe(200.0)
        ->and(BudgetBreakdownTotal::forFundCode($breakdown, 277, BudgetBreakdownTotal::STYLE_TRAVEL_LENIENT))->toBe(400.0);
});

it('matches change request view days logic', function () {
    $breakdown = [
        '277' => [
            ['unit_cost' => 10, 'units' => 2, 'days' => 1],
            ['unit_cost' => 10, 'units' => 2, 'days' => 3],
        ],
    ];

    expect(BudgetBreakdownTotal::forFundCode($breakdown, 277, BudgetBreakdownTotal::STYLE_CHANGE_REQUEST))->toBe(80.0);
});
