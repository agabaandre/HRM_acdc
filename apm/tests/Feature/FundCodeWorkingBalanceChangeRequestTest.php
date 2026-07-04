<?php

use App\Models\ChangeRequest;
use App\Services\FundCodeWorkingBalanceService;
use Illuminate\Support\Carbon;

test('selects latest change request per parent memo only', function () {
    $service = new FundCodeWorkingBalanceService();

    $older = ChangeRequest::make([
        'activity_id' => 100,
        'budget_breakdown' => [],
    ]);
    $older->forceFill(['id' => 10]);
    $older->updated_at = Carbon::parse('2026-01-01 00:00:00');

    $newer = ChangeRequest::make([
        'activity_id' => 100,
        'budget_breakdown' => [],
    ]);
    $newer->forceFill(['id' => 20]);
    $newer->updated_at = Carbon::parse('2026-06-01 00:00:00');

    $otherParent = ChangeRequest::make([
        'special_memo_id' => 50,
        'budget_breakdown' => [],
    ]);
    $otherParent->forceFill(['id' => 30]);

    $latest = $service->selectLatestChangeRequestsPerParent([$older, $newer, $otherParent]);

    expect($latest)->toHaveCount(2)
        ->and($latest['activity:100']->id)->toBe(20)
        ->and($latest['special_memo:50']->id)->toBe(30);
});

test('falls back to highest id when updated_at matches', function () {
    $service = new FundCodeWorkingBalanceService();
    $ts = Carbon::parse('2026-03-01 00:00:00');

    $first = ChangeRequest::make(['activity_id' => 7]);
    $first->forceFill(['id' => 5]);
    $first->updated_at = $ts;
    $second = ChangeRequest::make(['activity_id' => 7]);
    $second->forceFill(['id' => 9]);
    $second->updated_at = $ts;

    $latest = $service->selectLatestChangeRequestsPerParent([$first, $second]);

    expect($latest['activity:7']->id)->toBe(9);
});
