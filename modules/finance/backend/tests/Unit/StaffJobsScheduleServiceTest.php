<?php

namespace Tests\Unit;

use Modules\Jobs\Services\StaffJobsScheduleService;
use Tests\TestCase;

class StaffJobsScheduleServiceTest extends TestCase
{
    public function test_default_profile_completion_reminder_is_off(): void
    {
        $service = new StaffJobsScheduleService;
        $defaults = $service->defaults();

        $this->assertFalse($defaults['staff_profile_completion_reminder']);
        $this->assertFalse($service->resolved()['staff_profile_completion_reminder']);
    }

    public function test_normalize_keeps_enabled_time_spec(): void
    {
        $service = new StaffJobsScheduleService;
        $defaults = $service->defaults();

        $norm = $service->normalizeKey(
            'staff_birthday',
            ['hour' => 4, 'minute' => 15],
            $defaults,
        );

        $this->assertSame(['hour' => 4, 'minute' => 15], $norm);
    }

    public function test_from_request_disables_unchecked_daily_job(): void
    {
        $service = new StaffJobsScheduleService;
        $schedule = $service->fromRequest([
            'send_instant_mails' => true,
            'send_mails_interval_minutes' => 15,
            'staff_profile_completion_reminder_enabled' => false,
            'staff_birthday_enabled' => true,
            'staff_birthday_hour' => 3,
            'staff_birthday_minute' => 0,
            'manage_accounts_hourly_minute' => '',
        ]);

        $this->assertFalse($schedule['staff_profile_completion_reminder']);
        $this->assertSame(['hour' => 3, 'minute' => 0], $schedule['staff_birthday']);
        $this->assertNull($schedule['manage_accounts_hourly_minute']);
    }
}
