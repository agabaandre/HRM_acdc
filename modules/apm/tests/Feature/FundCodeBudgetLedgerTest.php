<?php

use App\Models\ChangeRequest;
use App\Services\BudgetCommitmentSettings;
use App\Services\FundCodeBudgetLedgerService;
use App\Services\FundCodeWorkingBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

test('ledger cache remember falls back when store fails', function () {
    Cache::partialMock()
        ->shouldReceive('remember')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));

    $service = app(FundCodeBudgetLedgerService::class);
    $method = new ReflectionMethod($service, 'cacheRemember');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'apm:ledger:test', 45, fn () => ['ok' => true]);

    expect($result)->toBe(['ok' => true]);
});

test('ledger marks superseded parent activity as skipped', function () {
    $settings = Mockery::mock(BudgetCommitmentSettings::class);
    $settings->shouldReceive('committedActivityStatuses')->andReturn(['draft', 'pending', 'submitted', 'approved']);
    $settings->shouldReceive('draftBudgetCutoff')->andReturn(null);

    $service = new FundCodeBudgetLedgerService(app(FundCodeWorkingBalanceService::class), $settings);
    $classify = new ReflectionMethod($service, 'classifyActivity');
    $classify->setAccessible(true);

    $cr = ChangeRequest::make(['activity_id' => 100, 'budget_breakdown' => []]);
    $cr->forceFill(['id' => 55]);
    $cr->updated_at = Carbon::now();

    $activeCrs = [
        'activity_ids' => [100],
        'special_memo_ids' => [],
        'non_travel_memo_ids' => [],
        'by_id' => [55 => $cr],
    ];

    [$committed, $code, $reason] = $classify->invoke(
        $service,
        'approved',
        Carbon::now(),
        'approved',
        100,
        $activeCrs,
        'activity'
    );

    expect($committed)->toBeFalse()
        ->and($code)->toBe('superseded_by_change_request')
        ->and($reason)->toContain('#55');
});

test('ledger marks non latest change request as skipped', function () {
    $settings = Mockery::mock(BudgetCommitmentSettings::class);
    $settings->shouldReceive('committedChangeRequestStatuses')->andReturn(['draft', 'pending', 'submitted']);
    $settings->shouldReceive('draftBudgetCutoff')->andReturn(null);

    $service = new FundCodeBudgetLedgerService(app(FundCodeWorkingBalanceService::class), $settings);
    $classify = new ReflectionMethod($service, 'classifyChangeRequest');
    $classify->setAccessible(true);

    $cr = ChangeRequest::make(['overall_status' => 'pending', 'budget_breakdown' => []]);
    $cr->forceFill(['id' => 10]);
    $cr->updated_at = Carbon::now();

    [$committed, $code] = $classify->invoke(
        $service,
        $cr,
        ['by_id' => []],
        [99]
    );

    expect($committed)->toBeFalse()
        ->and($code)->toBe('not_latest_change_request');
});
