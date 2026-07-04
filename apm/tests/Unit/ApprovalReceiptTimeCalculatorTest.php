<?php

namespace Tests\Unit;

use App\Services\ApprovalReceiptTimeCalculator;
use PHPUnit\Framework\TestCase;

class ApprovalReceiptTimeCalculatorTest extends TestCase
{
    public function test_received_time_sql_anchors_to_last_return_before_action(): void
    {
        $sql = (new ApprovalReceiptTimeCalculator)->receivedTimeCaseSql('at');

        $this->assertStringContainsString("ret.action = 'returned'", $sql);
        $this->assertStringContainsString('ret.updated_at < at.updated_at', $sql);
        $this->assertStringContainsString("sub_at.action IN ('submitted', 'resubmitted')", $sql);
        $this->assertStringContainsString('sub_at.updated_at >', $sql);
        $this->assertStringContainsString('prev_at.updated_at >', $sql);
    }

    public function test_workflow_stats_submitted_time_sql_uses_post_return_submission(): void
    {
        $sql = ApprovalReceiptTimeCalculator::workflowStatsSubmittedTimeSql();

        $this->assertStringContainsString("ret.action = 'returned'", $sql);
        $this->assertStringContainsString("sub_t.action IN ('submitted', 'resubmitted')", $sql);
        $this->assertStringContainsString('sub_t.updated_at > COALESCE', $sql);
    }
}
