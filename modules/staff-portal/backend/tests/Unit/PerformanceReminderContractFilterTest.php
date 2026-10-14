<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jobs\Services\EmailNotificationService;
use Modules\Jobs\Services\PerformanceReminderService;
use Modules\Performance\Enums\PerformancePhase;
use Tests\TestCase;

class PerformanceReminderContractFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jobs.schedule.portal_base_url' => 'https://cbp.example/staff/',
            'jobs.schedule.mail_logo_url' => 'https://cbp.example/logo.png',
            'jobs.schedule.system_email' => 'system@example.test',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('title')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
        });
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->integer('staff_id');
            $table->integer('status_id')->nullable();
            $table->integer('contract_type_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        Schema::create('ppa_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->date('ppa_start')->nullable();
            $table->date('ppa_deadline')->nullable();
            $table->date('mid_term_start')->nullable();
            $table->date('mid_term_deadline')->nullable();
            $table->date('end_term_start')->nullable();
            $table->date('end_term_deadline')->nullable();
            $table->boolean('ppa_requires_second_supervisor')->default(false);
            $table->boolean('midterm_requires_second_supervisor')->default(false);
            $table->boolean('endterm_requires_second_supervisor')->default(true);
            $table->boolean('endterm_requires_employee_consent')->default(true);
            $table->integer('allow_supervisor_return')->default(1);
        });
        DB::table('ppa_configs')->insert(['id' => 1]);

        $mail = \Mockery::mock(EmailNotificationService::class);
        $mail->shouldReceive('entryExists')->andReturn(false)->byDefault();
        $mail->shouldReceive('appendSystemInbox')->andReturnUsing(fn (string $e) => $e);
        $mail->shouldReceive('purgeTestRecipients')->byDefault();
        $mail->shouldReceive('render')->andReturn('body');
        $mail->shouldReceive('queue')->andReturn(true)->byDefault();
        $this->app->instance(EmailNotificationService::class, $mail);
    }

    public function test_active_reminder_periods_exclude_previous_year_for_ppa_and_midterm(): void
    {
        $service = app(PerformanceReminderService::class);
        $y = (int) date('Y');
        $current = "January-{$y}-to-December-{$y}";

        $this->assertSame([$current], $service->activeReminderPeriods(PerformancePhase::Ppa));
        $this->assertSame([$current], $service->activeReminderPeriods(PerformancePhase::Midterm));
        $this->assertNotContains(
            'January-'.($y - 1).'-to-December-'.($y - 1),
            $service->activeReminderPeriods(PerformancePhase::Ppa)
        );
    }

    public function test_staff_without_active_latest_contract_is_excluded(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 10, 'fname' => 'Gone', 'lname' => 'Staff', 'work_email' => 'gone@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 10,
            'status_id' => 3, // expired
            'contract_type_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
        ]);

        $service = app(PerformanceReminderService::class);
        $this->assertFalse($service->staffHasAllowedContract(10));
        $this->assertFalse($service->staffHasAllowedContract(10, 'January-2026-to-December-2026'));
    }

    public function test_staff_must_have_had_contract_covering_the_period(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 11, 'fname' => 'New', 'lname' => 'Hire', 'work_email' => 'new@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 11,
            'status_id' => 1,
            'contract_type_id' => 2,
            'start_date' => '2026-06-01',
            'end_date' => null,
        ]);

        $service = app(PerformanceReminderService::class);
        $this->assertTrue($service->staffHasAllowedContract(11));
        $this->assertTrue($service->staffHasAllowedContract(11, 'January-2026-to-December-2026'));
        $this->assertFalse($service->staffHasAllowedContract(11, 'January-2024-to-December-2024'));
    }

    public function test_active_staff_with_period_overlap_via_prior_contract_is_included(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 12, 'fname' => 'Keep', 'lname' => 'On', 'work_email' => 'keep@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            [
                'staff_id' => 12,
                'status_id' => 3,
                'contract_type_id' => 2,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ],
            [
                'staff_id' => 12,
                'status_id' => 1,
                'contract_type_id' => 2,
                'start_date' => '2026-01-01',
                'end_date' => null,
            ],
        ]);

        $service = app(PerformanceReminderService::class);
        $this->assertTrue($service->staffHasAllowedContract(12, 'January-2025-to-December-2025'));
        $this->assertTrue($service->staffHasAllowedContract(12, 'January-2026-to-December-2026'));
    }
}
