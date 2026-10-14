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

    public function test_send_instant_reclaims_overdue_next_dispatch(): void
    {
        DB::table('email_notifications')->insert([
            'entry_id' => 'perf-old',
            'staff_id' => 50,
            'subject' => 'Reminder: Pending performance approvals',
            'email_to' => 'alice@example.test',
            'body' => '<p>Hello</p>',
            'status' => '0',
            'end_date' => '2026-10-10',
            'next_dispatch' => '2026-10-10',
            'trigger' => 'Staff Portal System',
            'created_at' => '2026-10-10 13:00:00',
        ]);

        $mailer = \Mockery::mock(\App\Services\PortalMailer::class);
        $mailer->shouldReceive('send')->once();
        $this->app->instance(\App\Services\PortalMailer::class, $mailer);

        $result = app(SendQueuedMailService::class)->sendInstant();

        $this->assertSame(1, $result['sent']);
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
