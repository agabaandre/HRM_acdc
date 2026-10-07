<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jobs\Services\EmailNotificationService;
use Modules\Jobs\Services\PerformanceReminderService;
use Tests\TestCase;

class PerformanceReminderRoutingTest extends TestCase
{
    /** @var list<array{staff_id: int, subject: string}> */
    private array $queued = [];

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

        $this->createTables();
        $this->seedFixtures();
        $this->queued = [];
        $this->bindMailMock();
    }

    public function test_ppa_reminder_goes_only_to_current_first_approver(): void
    {
        DB::table('ppa_configs')->update(['ppa_requires_second_supervisor' => 1]);
        $this->insertPpaEntry([
            'entry_id' => 'ppa-route-first',
            'draft_status' => 0,
            'staff_sign_off' => 1,
            'performance_period' => 'January-2026-to-December-2026',
        ]);

        $queued = app(PerformanceReminderService::class)->notifySupervisorsPendingPerformanceApproval();

        $this->assertGreaterThan(0, $queued);
        $recipients = array_column($this->queued, 'staff_id');
        $this->assertContains(50, $recipients);
        $this->assertNotContains(51, $recipients);
    }

    public function test_ppa_reminder_goes_to_second_approver_after_first_approves(): void
    {
        DB::table('ppa_configs')->update(['ppa_requires_second_supervisor' => 1]);
        $this->insertPpaEntry([
            'entry_id' => 'ppa-route-second',
            'draft_status' => 0,
            'staff_sign_off' => 1,
            'performance_period' => 'January-2026-to-December-2026',
        ]);
        DB::table('ppa_approval_trail')->insert([
            'entry_id' => 'ppa-route-second',
            'staff_id' => 50,
            'comments' => 'ok',
            'action' => 'Approved',
            'created_at' => '2026-03-01 10:00:00',
        ]);

        $queued = app(PerformanceReminderService::class)->notifySupervisorsPendingPerformanceApproval();

        $this->assertGreaterThan(0, $queued);
        $recipients = array_column($this->queued, 'staff_id');
        $this->assertContains(51, $recipients);
        $this->assertNotContains(50, $recipients);
    }

    public function test_combined_reminder_includes_endterm_for_current_actor_only(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-route-endterm',
            'draft_status' => 2,
            'staff_sign_off' => 1,
            'performance_period' => 'January-2026-to-December-2026',
            'endterm_created_at' => '2026-10-01 10:00:00',
            'endterm_draft_status' => 0,
            'endterm_sign_off' => 1,
            'endterm_supervisor_1' => 50,
            'endterm_supervisor_2' => 51,
        ]);

        $queued = app(PerformanceReminderService::class)->notifySupervisorsPendingPerformanceApproval();

        $this->assertGreaterThan(0, $queued);
        $recipients = array_column($this->queued, 'staff_id');
        $this->assertContains(50, $recipients);
        $this->assertNotContains(51, $recipients);

        $firstMail = collect($this->queued)->firstWhere('staff_id', 50);
        $this->assertNotNull($firstMail);
        $this->assertStringContainsString('endterm', strtolower((string) ($firstMail['body_types'] ?? 'endterm')));
    }

    private function bindMailMock(): void
    {
        $mail = \Mockery::mock(EmailNotificationService::class);
        $mail->shouldReceive('entryExists')->andReturn(false);
        $mail->shouldReceive('appendSystemInbox')->andReturnUsing(fn (string $email) => $email);
        $mail->shouldReceive('purgeTestRecipients')->byDefault();
        $mail->shouldReceive('render')->andReturnUsing(function (string $view, array $data = []): string {
            $types = collect($data['pending_list'] ?? [])
                ->map(fn ($row) => is_array($row) ? ($row['approval_type'] ?? '') : ($row->approval_type ?? ''))
                ->filter()
                ->implode(',');

            return 'types:'.$types;
        });
        $mail->shouldReceive('queue')->andReturnUsing(function (
            string $trigger,
            string $emailTo,
            string $body,
            string $subject,
            int $staffId,
        ) {
            $this->queued[] = [
                'staff_id' => $staffId,
                'subject' => $subject,
                'email_to' => $emailTo,
                'body_types' => str_replace('types:', '', $body),
            ];

            return true;
        });

        $this->app->instance(EmailNotificationService::class, $mail);
    }

    private function createTables(): void
    {
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
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
        });

        Schema::create('ppa_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('allow_supervisor_return')->default(1);
            $table->boolean('ppa_requires_second_supervisor')->default(false);
            $table->boolean('midterm_requires_second_supervisor')->default(false);
            $table->boolean('endterm_requires_second_supervisor')->default(true);
            $table->boolean('endterm_requires_employee_consent')->default(true);
            $table->date('ppa_start')->nullable();
            $table->date('ppa_deadline')->nullable();
            $table->date('mid_term_start')->nullable();
            $table->date('mid_term_deadline')->nullable();
            $table->date('end_term_start')->nullable();
            $table->date('end_term_deadline')->nullable();
        });

        Schema::create('ppa_entries', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id');
            $table->integer('staff_contract_id')->nullable();
            $table->string('performance_period', 50);
            $table->string('entry_id', 100)->unique();
            $table->integer('supervisor_id')->nullable();
            $table->integer('supervisor2_id')->nullable();
            $table->boolean('staff_sign_off')->default(false);
            $table->tinyInteger('draft_status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('midterm_created_at')->nullable();
            $table->dateTime('midterm_updated_at')->nullable();
            $table->tinyInteger('midterm_draft_status')->default(1);
            $table->boolean('midterm_sign_off')->default(false);
            $table->integer('midterm_supervisor_1')->nullable();
            $table->integer('midterm_supervisor_2')->nullable();
            $table->dateTime('endterm_created_at')->nullable();
            $table->dateTime('endterm_updated_at')->nullable();
            $table->tinyInteger('endterm_draft_status')->default(1);
            $table->boolean('endterm_sign_off')->default(false);
            $table->integer('endterm_supervisor_1')->nullable();
            $table->integer('endterm_supervisor_2')->nullable();
            $table->boolean('endterm_supervisor1_discussion_confirmed')->default(false);
            $table->boolean('endterm_staff_discussion_confirmed')->default(false);
            $table->dateTime('endterm_staff_consent_at')->nullable();
            $table->boolean('endterm_supervisor2_agreement')->nullable();
        });

        Schema::create('ppa_approval_trail', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 100);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('ppa_approval_trail_midterm', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 100);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at')->nullable();
            $table->string('type', 20)->default('PPA');
        });

        Schema::create('ppa_approval_trail_end_term', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 255);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at');
            $table->string('type')->nullable();
        });
    }

    private function seedFixtures(): void
    {
        DB::table('staff')->insert([
            ['staff_id' => 50, 'title' => 'Ms', 'fname' => 'Alice', 'lname' => 'Supervisor', 'work_email' => 'alice@example.test'],
            ['staff_id' => 51, 'title' => 'Mr', 'fname' => 'Bob', 'lname' => 'Supervisor', 'work_email' => 'bob@example.test'],
            ['staff_id' => 100, 'title' => 'Dr', 'fname' => 'Current', 'lname' => 'Staff', 'work_email' => 'staff@example.test'],
        ]);

        foreach ([50, 51, 100] as $staffId) {
            DB::table('staff_contracts')->insert([
                'staff_id' => $staffId,
                'status_id' => 1,
                'contract_type_id' => 2,
                'start_date' => '2020-01-01',
                'end_date' => null,
                'first_supervisor' => 50,
                'second_supervisor' => 51,
            ]);
        }

        DB::table('ppa_configs')->insert([
            'id' => 1,
            'allow_supervisor_return' => 1,
            'ppa_requires_second_supervisor' => 0,
            'midterm_requires_second_supervisor' => 0,
            'endterm_requires_second_supervisor' => 1,
            'endterm_requires_employee_consent' => 1,
            'ppa_start' => '2026-01-01',
            'ppa_deadline' => '2026-12-31',
            'mid_term_start' => '2026-01-01',
            'mid_term_deadline' => '2026-12-31',
            'end_term_start' => '2026-01-01',
            'end_term_deadline' => '2026-12-31',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertPpaEntry(array $attributes): void
    {
        DB::table('ppa_entries')->insert(array_merge([
            'staff_id' => 100,
            'staff_contract_id' => 3,
            'performance_period' => 'January-2026-to-December-2026',
            'entry_id' => 'ppa-entry-default',
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'staff_sign_off' => 1,
            'draft_status' => 1,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $attributes));
    }
}
