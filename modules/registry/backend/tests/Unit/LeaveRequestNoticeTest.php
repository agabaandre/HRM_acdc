<?php

namespace Tests\Unit;

use Carbon\Carbon;
use InvalidArgumentException;
use Mockery;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Leave\Services\LeaveRequestService;
use Tests\TestCase;

class LeaveRequestNoticeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_earliest_start_is_today_plus_configured_notice_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));

        $service = $this->serviceWithNoticeDays(7);

        $this->assertSame(7, $service->minNoticeDays());
        $this->assertSame('2026-08-21', $service->earliestAllowedStartDate());
    }

    public function test_zero_notice_still_blocks_past_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));

        $service = $this->serviceWithNoticeDays(0);

        $this->assertSame('2026-08-14', $service->earliestAllowedStartDate());
        $service->assertApplicationDates('2026-08-14', '2026-08-15');
    }

    public function test_rejects_start_date_inside_the_notice_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));

        $service = $this->serviceWithNoticeDays(7);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 7 days');

        $service->assertApplicationDates('2026-08-18', '2026-08-22');
    }

    public function test_rejects_a_start_date_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));

        $service = $this->serviceWithNoticeDays(0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be in the past');

        $service->assertApplicationDates('2026-08-13', '2026-08-14');
    }

    public function test_accepts_start_date_on_the_earliest_allowed_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00'));

        $service = $this->serviceWithNoticeDays(7);
        $service->assertApplicationDates('2026-08-21', '2026-08-25');

        $this->assertTrue(true);
    }

    protected function serviceWithNoticeDays(int $days): LeaveRequestService
    {
        $policy = Mockery::mock(LeavePolicyService::class);
        $policy->shouldReceive('get')
            ->with('application_min_notice_days', 7)
            ->andReturn($days);

        $balances = Mockery::mock(LeaveBalanceService::class);

        return new LeaveRequestService($balances, $policy);
    }
}
