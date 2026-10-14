<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jobs\Services\EmailNotificationService;
use Modules\Jobs\Services\SendQueuedMailService;
use Tests\TestCase;

class SendQueuedMailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jobs.schedule.system_email' => 'system@example.test',
            'jobs.schedule.portal_base_url' => 'https://cbp.example/staff/',
            'jobs.schedule.mail_logo_url' => 'https://cbp.example/logo.png',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('email_notifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id')->nullable();
            $table->integer('staff_id')->nullable();
            $table->string('subject');
            $table->string('email_to');
            $table->text('body')->nullable();
            $table->string('status')->nullable();
            $table->string('end_date')->nullable();
            $table->string('next_dispatch')->nullable();
            $table->string('trigger')->nullable();
            $table->timestamp('created_at')->nullable();
        });
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
        DB::table('staff')->insert([
            'staff_id' => 50, 'fname' => 'Alice', 'lname' => 'Supervisor', 'work_email' => 'alice@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 50,
            'status_id' => 1,
            'contract_type_id' => 2,
            'start_date' => ((int) date('Y') - 1).'-01-01',
            'end_date' => null,
        ]);
    }

    public function test_send_instant_includes_pending_performance_approvals_subject(): void
    {
        $today = date('Y-m-d');
        DB::table('email_notifications')->insert([
            'entry_id' => 'perf-1',
            'staff_id' => 50,
            'subject' => 'Reminder: Pending performance approvals',
            'email_to' => 'alice@example.test;system@example.test',
            'body' => '<p>Hello</p>',
            'status' => '0',
            'end_date' => $today,
            'next_dispatch' => $today,
            'trigger' => 'Staff Portal System',
            'created_at' => now()->toDateTimeString(),
        ]);

        $subjects = [];
        $mailer = \Mockery::mock(\App\Services\PortalMailer::class);
        $mailer->shouldReceive('send')->once()->andReturnUsing(function ($to, string $subject) use (&$subjects): void {
            $subjects[] = $subject;
        });
        $this->app->instance(\App\Services\PortalMailer::class, $mailer);

        $result = app(SendQueuedMailService::class)->sendInstant();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(['Reminder: Pending performance approvals'], $subjects);
        $this->assertSame('1', (string) DB::table('email_notifications')->where('entry_id', 'perf-1')->value('status'));
    }

    public function test_send_instant_abandons_closed_period_reminders_and_reclaims_recent(): void
    {
        $today = date('Y-m-d');
        $y = (int) date('Y');
        $period = "January-{$y}-to-December-{$y}";

        DB::table('email_notifications')->insert([
            [
                'entry_id' => 'perf-recent',
                'staff_id' => 50,
                'subject' => "Reminder: Pending PPA Approvals for {$period}",
                'email_to' => 'alice@example.test',
                'body' => '<p>Hello</p>',
                'status' => '0',
                'end_date' => $today,
                'next_dispatch' => date('Y-m-d', strtotime('-1 day')),
                'trigger' => 'Staff Portal System',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
            [
                'entry_id' => 'perf-ancient',
                'staff_id' => 50,
                'subject' => 'Staff PPA Reminder: Submit your PPA (January-2025-to-December-2025)',
                'email_to' => 'alice@example.test',
                'body' => '<p>Hello</p>',
                'status' => '0',
                'end_date' => '2025-12-31',
                'next_dispatch' => '2025-12-01',
                'trigger' => 'Staff Portal System',
                'created_at' => '2025-11-01 10:00:00',
            ],
        ]);

        $mailer = \Mockery::mock(\App\Services\PortalMailer::class);
        $mailer->shouldReceive('send')->once();
        $this->app->instance(\App\Services\PortalMailer::class, $mailer);

        $result = app(SendQueuedMailService::class)->sendInstant();

        $this->assertSame(1, $result['sent']);
        // Closed-period rows are abandoned (status=1) and then pruned by the week-old cleanup.
        $this->assertSame(0, DB::table('email_notifications')->where('entry_id', 'perf-ancient')->count());
        $this->assertSame('1', (string) DB::table('email_notifications')->where('entry_id', 'perf-recent')->value('status'));
    }

    public function test_entry_exists_only_counts_successfully_sent_rows(): void
    {
        DB::table('email_notifications')->insert([
            'entry_id' => 'dup-1',
            'staff_id' => 50,
            'subject' => 'Reminder: Pending performance approvals',
            'email_to' => 'alice@example.test',
            'body' => '<p>Hello</p>',
            'status' => '0',
            'end_date' => date('Y-m-d'),
            'next_dispatch' => date('Y-m-d'),
            'trigger' => 'Staff Portal System',
            'created_at' => now()->toDateTimeString(),
        ]);

        $mail = app(EmailNotificationService::class);
        $this->assertFalse($mail->entryExists('dup-1'));

        DB::table('email_notifications')->where('entry_id', 'dup-1')->update(['status' => '1']);
        $this->assertTrue($mail->entryExists('dup-1'));
    }
}
