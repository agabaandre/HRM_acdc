<?php

namespace Tests\Unit;

use App\Models\WeeklyBriefingReport;
use PHPUnit\Framework\TestCase;

class WeeklyBriefingIsoWeekLabelsTest extends TestCase
{
    public function test_previous_iso_week_pair_from_week_two_steps_back(): void
    {
        $previous = WeeklyBriefingReport::previousIsoWeekPair(2026, 21);

        $this->assertSame(2026, $previous['iso_year']);
        $this->assertSame(20, $previous['iso_week']);
    }

    public function test_previous_iso_week_pair_crosses_year_boundary(): void
    {
        $previous = WeeklyBriefingReport::previousIsoWeekPair(2026, 1);

        $this->assertSame(2025, $previous['iso_year']);
        $this->assertSame(52, $previous['iso_week']);
    }

    public function test_inline_range_uses_short_weekday_names(): void
    {
        $label = WeeklyBriefingReport::humanIsoWeekRangeInline(2026, 21);

        $this->assertStringContainsString('Mon,', $label);
        $this->assertStringContainsString('Sun,', $label);
        $this->assertStringContainsString('(W21/2026)', $label);
        $this->assertStringNotContainsString('ISO', $label);
    }

    public function test_filter_option_label_includes_week_and_dates(): void
    {
        $label = WeeklyBriefingReport::isoWeekFilterOptionLabel(2026, 21);

        $this->assertStringStartsWith('W21 ·', $label);
        $this->assertStringContainsString('Mon,', $label);
        $this->assertStringContainsString('Sun,', $label);
    }
}
