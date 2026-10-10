<?php

use App\Services\Pra\PraActivitiesCacheService;
use App\Services\Pra\PraClient;
use App\Services\Pra\PraOrgUnitMappingService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Mockery::close();
    Cache::flush();
});

test('matches quarter by date overlap and by quarter budget fallback', function () {
    $client = Mockery::mock(PraClient::class);
    $settings = Mockery::mock(PraSettingsService::class);
    $mappings = Mockery::mock(PraOrgUnitMappingService::class);
    $svc = new PraActivitiesCacheService($client, $settings, $mappings);

    expect($svc->matchesQuarter([
        'start_date' => '2026-04-15',
        'end_date' => '2026-05-20',
        'q2_budget' => 0,
    ], 2026, 'Q2'))->toBeTrue();

    expect($svc->matchesQuarter([
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-01',
        'q2_budget' => 0,
    ], 2026, 'Q2'))->toBeFalse();

    expect($svc->matchesQuarter([
        'start_date' => null,
        'end_date' => null,
        'q1_budget' => 0,
        'q2_budget' => 1000,
        'q3_budget' => 0,
        'q4_budget' => 0,
    ], 2026, 'Q2'))->toBeTrue();
});

test('activities for division return all rows with matches_quarter hint', function () {
    $client = Mockery::mock(PraClient::class);
    $settings = Mockery::mock(PraSettingsService::class);
    $settings->shouldReceive('resolved')->andReturn([
        'fiscal_year' => 2099,
        'division_aliases' => [],
    ]);
    $settings->shouldReceive('isConfigured')->andReturn(true);
    $mappings = Mockery::mock(PraOrgUnitMappingService::class);

    $svc = Mockery::mock(PraActivitiesCacheService::class, [$client, $settings, $mappings])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    Cache::store('redis')->put('apm:pra:activities:fy:2099', [
        'fiscal_year' => 2099,
        'cached_at' => now()->toIso8601String(),
        'activities' => [
            [
                'pra_activity_id' => 1,
                'code' => 'A.1',
                'division_code' => 'MIS',
                'start_date' => '2099-01-10',
                'end_date' => '2099-02-10',
                'q1_budget' => 10,
                'q2_budget' => 0,
            ],
            [
                'pra_activity_id' => 2,
                'code' => 'A.2',
                'division_code' => 'MIS',
                'start_date' => '2099-05-01',
                'end_date' => '2099-06-01',
                'q1_budget' => 0,
                'q2_budget' => 20,
            ],
        ],
    ], 60);

    $svc->shouldReceive('praCodesForLocalDivision')->andReturn(['MIS']);

    $all = $svc->activitiesForDivision(21, 2099, 'Q1', false);
    expect($all)->toHaveCount(2)
        ->and($all[0]['matches_quarter'])->toBeTrue()
        ->and($all[1]['matches_quarter'])->toBeFalse();

    Cache::store('redis')->forget('apm:pra:activities:fy:2099');
});

test('refresh flattens specific activities into redis cache', function () {
    $client = Mockery::mock(PraClient::class);
    $client->shouldReceive('fetchWorkplan')->once()->andReturn([
        'data' => [[
            'id' => 10,
            'code' => 'IND.1',
            'title' => 'Indicator outcome',
            'specific_activities' => [[
                'id' => 99,
                'code' => '1.2.3',
                'title' => 'Do the thing',
                'status' => 'APPROVED',
                'division' => ['code' => 'MIS', 'name' => 'MIS'],
                'broad_activity' => ['id' => 5, 'code' => '1.2', 'title' => 'Broad outcome'],
                'start_date' => '2026-04-01',
                'end_date' => '2026-06-30',
                'q2_budget' => 50,
            ]],
        ]],
    ]);

    $settings = Mockery::mock(PraSettingsService::class);
    $settings->shouldReceive('resolved')->andReturn([
        'fiscal_year' => 2026,
        'division_aliases' => [],
    ]);
    $settings->shouldReceive('isConfigured')->andReturn(true);

    $mappings = Mockery::mock(PraOrgUnitMappingService::class);
    $svc = new PraActivitiesCacheService($client, $settings, $mappings);

    $result = $svc->refresh(2099);
    expect($result['activities'])->toBe(1);

    $found = $svc->findActivity(99, 2099);
    expect($found)->not->toBeNull()
        ->and($found['pra_activity_id'])->toBe(99)
        ->and($found['outcome_area'])->toBe('Broad outcome')
        ->and($found['division_code'])->toBe('MIS');

    Cache::store('redis')->forget('apm:pra:activities:fy:2099');
});
