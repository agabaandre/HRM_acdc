<?php

namespace Tests\Unit;

use App\Services\FundCodeWorkingBalanceService;
use App\Support\BudgetBreakdownTotal;
use PHPUnit\Framework\TestCase;

class FundCodeWorkingBalanceServiceTest extends TestCase
{
    public function test_sum_breakdown_for_fund_code_with_days(): void
    {
        $service = new FundCodeWorkingBalanceService();
        $breakdown = [
            246 => [
                ['unit_cost' => 100, 'units' => 2, 'days' => 3],
                ['unit_cost' => 50, 'units' => 1, 'days' => 1],
            ],
        ];

        $this->assertSame(650.0, $service->sumBreakdownForFundCode($breakdown, 246, BudgetBreakdownTotal::STYLE_TRAVEL_STRICT));
    }

    public function test_sum_breakdown_for_fund_code_with_quantity(): void
    {
        $service = new FundCodeWorkingBalanceService();
        $breakdown = [
            246 => [
                ['unit_cost' => 25, 'quantity' => 4],
            ],
        ];

        $this->assertSame(100.0, $service->sumBreakdownForFundCode($breakdown, 246, BudgetBreakdownTotal::STYLE_NON_TRAVEL));
    }

    public function test_breakdown_totals_per_code(): void
    {
        $service = new FundCodeWorkingBalanceService();
        $breakdown = [
            10 => [['unit_cost' => 10, 'units' => 2, 'days' => 1]],
            20 => [['unit_cost' => 5, 'quantity' => 3]],
        ];

        $totals = $service->breakdownTotalsPerCode($breakdown, false, true);
        $this->assertSame(20.0, $totals[10]);

        $nonTravelTotals = $service->breakdownTotalsPerCode([
            20 => [['unit_cost' => 5, 'quantity' => 3]],
        ], true, false);
        $this->assertSame(15.0, $nonTravelTotals[20]);
    }

    public function test_archived_status_does_not_commit_funds(): void
    {
        $this->assertContains('archived', FundCodeWorkingBalanceService::NON_COMMITTING_STATUSES);
        $this->assertNotContains('archived', FundCodeWorkingBalanceService::COMMITTED_ACTIVITY_STATUSES);
        $this->assertNotContains('archived', FundCodeWorkingBalanceService::COMMITTED_MEMO_STATUSES);
        $this->assertNotContains('archived', FundCodeWorkingBalanceService::ACTIVE_CHANGE_REQUEST_STATUSES);
    }
}
