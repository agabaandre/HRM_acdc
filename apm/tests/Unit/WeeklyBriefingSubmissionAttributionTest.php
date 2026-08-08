<?php

namespace Tests\Unit;

use App\Models\WeeklyBriefingReport;
use PHPUnit\Framework\TestCase;

class WeeklyBriefingSubmissionAttributionTest extends TestCase
{
    public function test_submission_filed_on_behalf_trail_round_trip(): void
    {
        $report = new WeeklyBriefingReport;
        $report->appendSubmissionFiledOnBehalfTrail(42, 99);

        $this->assertSame(42, $report->submissionFiledOnBehalfByStaffId());
        $trail = $report->director_review_trail;
        $this->assertIsArray($trail);
        $this->assertSame('submitted_on_behalf', $trail[0]['action'] ?? null);
        $this->assertSame(42, (int) ($trail[0]['staff_id'] ?? 0));
        $this->assertSame(99, (int) ($trail[0]['attributed_to_staff_id'] ?? 0));
    }

    public function test_submission_filed_on_behalf_uses_latest_entry(): void
    {
        $report = new WeeklyBriefingReport([
            'director_review_trail' => [
                [
                    'at' => '2026-01-01T00:00:00+00:00',
                    'staff_id' => 1,
                    'action' => 'submitted_on_behalf',
                    'attributed_to_staff_id' => 10,
                ],
            ],
        ]);
        $report->appendSubmissionFiledOnBehalfTrail(77, 88);

        $this->assertSame(77, $report->submissionFiledOnBehalfByStaffId());
    }
}
