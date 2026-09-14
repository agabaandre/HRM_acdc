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

it('original memo total matches the service-request budget table including days of zero', function () {
    $breakdown = [
        '277' => [
            ['unit_cost' => 1650, 'units' => 6, 'days' => 0, 'cost' => 'Tickets'],
            ['unit_cost' => 100, 'units' => 6, 'days' => 1, 'cost' => 'Visa'],
            ['unit_cost' => 176, 'units' => 6, 'days' => 4, 'cost' => 'DSA'],
        ],
    ];

    // Table: days<=1 → unit_cost * units; days>1 → unit_cost * units * days
    // 1650*6 + 100*6 + 176*6*4 = 9900 + 600 + 4224 = 14724
    expect(BudgetBreakdownTotal::originalMemoTotalForSource('activity', $breakdown))->toBe(14724.0)
        ->and(BudgetBreakdownTotal::originalMemoTotalForSource('special_memo', $breakdown))->toBe(14724.0);
});

it('original memo total matches the edit-form budget breakdown subtotal', function () {
    $source = [
        '277' => [
            ['unit_cost' => 1650, 'units' => 6, 'days' => 1, 'cost' => 'Tickets'],
            ['unit_cost' => 100, 'units' => 6, 'days' => 1, 'cost' => 'Visa'],
            ['unit_cost' => 24, 'units' => 6, 'days' => 1, 'cost' => 'Terminal Fee'],
            ['unit_cost' => 176, 'units' => 6, 'days' => 4, 'cost' => 'DSA'],
            ['unit_cost' => 40, 'units' => 15, 'days' => 3, 'cost' => 'Stipend'],
            ['unit_cost' => 300, 'units' => 2, 'days' => 1, 'cost' => 'Banners'],
            ['unit_cost' => 300, 'units' => 1, 'days' => 3, 'cost' => 'Car Hire'],
            ['unit_cost' => 500, 'units' => 1, 'days' => 3, 'cost' => 'Conference'],
            ['unit_cost' => 55, 'units' => 25, 'days' => 3, 'cost' => 'Conference'],
        ],
    ];

    expect(BudgetBreakdownTotal::originalMemoTotalForSource('activity', $source))->toBe(23793.0);
});

it('edit-form original total uses the source memo not a stored request breakdown that cannot be summed', function () {
    $source = [
        '277' => [
            ['unit_cost' => 1650, 'units' => 6, 'days' => 1, 'cost' => 'Tickets'],
            ['unit_cost' => 100, 'units' => 6, 'days' => 1, 'cost' => 'Visa'],
            ['unit_cost' => 24, 'units' => 6, 'days' => 1, 'cost' => 'Terminal Fee'],
            ['unit_cost' => 176, 'units' => 6, 'days' => 4, 'cost' => 'DSA'],
            ['unit_cost' => 40, 'units' => 15, 'days' => 3, 'cost' => 'Stipend'],
            ['unit_cost' => 300, 'units' => 2, 'days' => 1, 'cost' => 'Banners'],
            ['unit_cost' => 300, 'units' => 1, 'days' => 3, 'cost' => 'Car Hire'],
            ['unit_cost' => 500, 'units' => 1, 'days' => 3, 'cost' => 'Conference'],
            ['unit_cost' => 55, 'units' => 25, 'days' => 3, 'cost' => 'Conference'],
        ],
    ];
    $storedRequestBreakdown = [
        '277' => [
            ['cost' => 'Tickets', 'quantity' => 6],
        ],
        'grand_total' => 0,
    ];

    expect(BudgetBreakdownTotal::originalMemoTotalForSource('activity', $storedRequestBreakdown))->toBe(0.0)
        ->and(BudgetBreakdownTotal::originalTotalForServiceRequestForm(
            'activity',
            $source,
            $storedRequestBreakdown,
            0.0,
        ))->toBe(23793.0);
});

it('decodes slash-escaped budget json when summing original memo total', function () {
    $json = addslashes(json_encode([
        '277' => [
            ['unit_cost' => 100, 'units' => 2, 'days' => 1],
        ],
    ]));

    expect(BudgetBreakdownTotal::originalMemoTotalForSource('activity', $json))->toBe(200.0);
});
