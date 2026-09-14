<?php

namespace Tests\Unit;

use App\Support\TicketListDatePreset;
use Carbon\Carbon;
use Tests\TestCase;

class TicketListDatePresetTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_all_and_unknown_presets_have_no_lower_bound(): void
    {
        $now = Carbon::parse('2026-08-26 15:30:00');

        $this->assertNull(TicketListDatePreset::createdSince('all', $now));
        $this->assertNull(TicketListDatePreset::createdSince('', $now));
        $this->assertNull(TicketListDatePreset::createdSince('yesterday', $now));
    }

    public function test_prefixed_presets_start_at_beginning_of_local_day(): void
    {
        $now = Carbon::parse('2026-08-26 15:30:00');

        $this->assertSame('2026-08-26 00:00:00', TicketListDatePreset::createdSince('today', $now)?->toDateTimeString());
        $this->assertSame('2026-08-24 00:00:00', TicketListDatePreset::createdSince('last_3_days', $now)?->toDateTimeString());
        $this->assertSame('2026-08-22 00:00:00', TicketListDatePreset::createdSince('last_5_days', $now)?->toDateTimeString());
        $this->assertSame('2026-08-20 00:00:00', TicketListDatePreset::createdSince('last_week', $now)?->toDateTimeString());
        $this->assertSame('2026-07-26 00:00:00', TicketListDatePreset::createdSince('last_month', $now)?->toDateTimeString());
        $this->assertSame('2026-05-26 00:00:00', TicketListDatePreset::createdSince('last_months', $now)?->toDateTimeString());
    }
}
