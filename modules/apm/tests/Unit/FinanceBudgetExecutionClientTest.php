<?php

use App\Services\FinanceBudgetExecutionClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    session([
        'user' => [
            'staff_id' => 1,
            'name' => 'Finance Proxy Test',
            'email' => 'proxy-test@africacdc.org',
            'division_id' => 6,
            'role' => 10,
            'permissions' => [92],
        ],
    ]);
});

test('finance budget execution client fetches and unwraps finance api payload', function () {
    Http::fake([
        '*/api/v1/budget-execution*' => Http::response([
            'success' => true,
            'data' => [
                'budget_year' => 2026,
                'kpis' => [
                    'approved_budget' => 1000,
                    'used_budget' => 400,
                    'execution_rate' => 0.4,
                    'fund_center_count' => 1,
                    'division_count' => 1,
                ],
                'divisions' => [],
                'filter_catalog' => [
                    'divisions' => [],
                    'fund_centers' => [],
                    'funds' => [],
                ],
            ],
        ], 200),
    ]);

    $client = new FinanceBudgetExecutionClient;
    $payload = $client->budgetExecution(['division_id' => 6]);

    expect($payload['success'])->toBeTrue()
        ->and($payload['data']['kpis']['approved_budget'])->toBe(1000);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/budget-execution')
            && $request->hasHeader('Authorization')
            && $request['division_id'] == 6;
    });
});
