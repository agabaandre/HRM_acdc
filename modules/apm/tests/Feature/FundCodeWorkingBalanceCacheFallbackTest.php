<?php

use App\Services\FundCodeWorkingBalanceService;
use Illuminate\Support\Facades\Cache;

test('cache remember falls back to callback when store fails', function () {
    Cache::partialMock()
        ->shouldReceive('remember')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));

    $service = new FundCodeWorkingBalanceService();
    $method = new ReflectionMethod($service, 'cacheRemember');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'apm:test', 60, fn () => ['working_balance' => 99.5]);

    expect($result)->toBe(['working_balance' => 99.5]);
});

test('cache get returns default when store fails', function () {
    Cache::partialMock()
        ->shouldReceive('get')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));

    $service = new FundCodeWorkingBalanceService();
    $method = new \ReflectionMethod($service, 'cacheGet');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'apm:fc:1:ver', 'fallback');

    expect($result)->toBe('fallback');
});

test('bust does not throw when cache write fails', function () {
    Cache::partialMock()
        ->shouldReceive('getStore')
        ->andThrow(new RuntimeException('Redis unavailable'));

    app(FundCodeWorkingBalanceService::class)->bust(1);

    expect(true)->toBeTrue();
});
