<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Performance\Http\Controllers\Api\V1\PerformanceHubApiController;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PpaFormService;
use Modules\Performance\Services\PpaSettingsService;
use Tests\TestCase;

class PerformanceApproverNavApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
        DB::purge();
        DB::reconnect();

        $this->withoutMiddleware();
        $this->createTables();
        $this->seedFixtures();
    }

    public function test_pending_count_matches_hub_pending_length(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-pending-count',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(50, permissions: [74]));

        $countResponse = app(PerformanceHubApiController::class)->pendingCount(
            app(PerformanceApprovalService::class),
        );
        $hubResponse = app(PerformanceHubApiController::class)->hub(
            Request::create('/api/v1/performance/hub', 'GET', ['tab' => 'pending']),
            app(PerformanceService::class),
            app(PerformanceApprovalService::class),
            app(PpaSettingsService::class),
            app(PpaFormService::class),
        );

        $countPayload = $countResponse->getData(true);
        $hubPayload = $hubResponse->getData(true);

        $this->assertSame(200, $countResponse->getStatusCode());
        $this->assertSame(200, $hubResponse->getStatusCode());
        $this->assertSame(
            (int) $hubPayload['data']['pending_count'],
            (int) $countPayload['data']['pending_count']
        );
        $this->assertGreaterThan(0, (int) $countPayload['data']['pending_count']);
        $this->assertArrayHasKey('staff_name', $hubPayload['data']['pending'][0]);
        $this->assertArrayNotHasKey('objectives', $hubPayload['data']['pending'][0]);
    }

    public function test_pending_includes_submitted_ppa_with_null_supervisor_via_contract(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-null-supervisor',
            'staff_id' => 100,
            'supervisor_id' => null,
            'supervisor2_id' => null,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(50, permissions: [74]));

        $hubResponse = app(PerformanceHubApiController::class)->hub(
            Request::create('/api/v1/performance/hub', 'GET', ['tab' => 'pending']),
            app(PerformanceService::class),
            app(PerformanceApprovalService::class),
            app(PpaSettingsService::class),
            app(PpaFormService::class),
        );

        $pending = $hubResponse->getData(true)['data']['pending'];
        $entryIds = array_column($pending, 'entry_id');

        $this->assertContains('ppa-entry-null-supervisor', $entryIds);
        $this->assertSame(50, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-null-supervisor')->value('supervisor_id'));
    }

    public function test_approval_history_returns_only_actor_approved_or_returned(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-history-a',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 2,
            'staff_sign_off' => 1,
        ]);
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-history-b',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'performance_period' => 'January-2025-to-December-2025',
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-history-c',
            'staff_id' => 100,
            'supervisor_id' => 51,
            'supervisor2_id' => 50,
            'performance_period' => 'January-2024-to-December-2024',
            'draft_status' => 2,
            'staff_sign_off' => 1,
        ]);

        DB::table('ppa_approval_trail')->insert([
            [
                'entry_id' => 'ppa-entry-history-a',
                'staff_id' => 50,
                'comments' => 'Looks good',
                'action' => 'Approved',
                'created_at' => '2026-03-01 10:00:00',
            ],
            [
                'entry_id' => 'ppa-entry-history-b',
                'staff_id' => 50,
                'comments' => 'Needs work',
                'action' => 'Returned',
                'created_at' => '2026-03-02 11:00:00',
            ],
            [
                'entry_id' => 'ppa-entry-history-c',
                'staff_id' => 51,
                'comments' => 'Other actor',
                'action' => 'Approved',
                'created_at' => '2026-03-03 12:00:00',
            ],
            [
                'entry_id' => 'ppa-entry-history-a',
                'staff_id' => 50,
                'comments' => 'Submitted',
                'action' => 'Submitted',
                'created_at' => '2026-02-28 09:00:00',
            ],
        ]);

        session()->put($this->portalSession(50, permissions: [74]));

        $response = app(PerformanceHubApiController::class)->approvalHistory(
            Request::create('/api/v1/performance/approval-history', 'GET'),
            app(PerformanceApprovalService::class),
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $payload['data']);
        $this->assertSame(2, (int) $payload['meta']['total']);

        foreach ($payload['data'] as $row) {
            $this->assertContains(strtolower((string) $row['action']), ['approved', 'returned']);
            $this->assertArrayHasKey('entry_id', $row);
            $this->assertArrayHasKey('staff_id', $row);
            $this->assertArrayHasKey('staff_name', $row);
            $this->assertArrayHasKey('phase', $row);
            $this->assertArrayHasKey('phase_label', $row);
            $this->assertArrayHasKey('performance_period', $row);
            $this->assertArrayHasKey('action', $row);
            $this->assertArrayHasKey('acted_at', $row);
            $this->assertArrayHasKey('form_url', $row);
            $this->assertSame(100, (int) $row['staff_id']);
            $this->assertStringContainsString('/performance/form/', (string) $row['form_url']);
        }

        $actions = collect($payload['data'])->pluck('action')->map(fn ($a) => strtolower((string) $a))->all();
        $this->assertContains('approved', $actions);
        $this->assertContains('returned', $actions);
        $this->assertSame('Current Staff', $payload['data'][0]['staff_name']);
    }

    public function test_my_history_lists_each_phase_with_pending_supervisor(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-my-history',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
            'midterm_created_at' => '2026-06-01 10:00:00',
            'midterm_draft_status' => 0,
            'midterm_supervisor_1' => 50,
            'midterm_supervisor_2' => 51,
            'endterm_created_at' => '2026-11-01 10:00:00',
            'endterm_draft_status' => 1,
            'endterm_supervisor_1' => 50,
            'endterm_supervisor_2' => 51,
        ]);

        session()->put($this->portalSession(100, permissions: [74]));

        $response = app(PerformanceHubApiController::class)->hub(
            Request::create('/api/v1/performance/hub', 'GET', [
                'tab' => 'my',
                'period' => 'January-2026-to-December-2026',
            ]),
            app(PerformanceService::class),
            app(PerformanceApprovalService::class),
            app(PpaSettingsService::class),
            app(PpaFormService::class),
        );

        $payload = $response->getData(true);
        $rows = $payload['data']['my_ppas']['data'];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(3, $rows);
        $this->assertSame(['ppa', 'midterm', 'endterm'], array_column($rows, 'phase'));

        $ppa = $rows[0];
        $this->assertSame('ppa', $ppa['phase']);
        $this->assertNotEmpty($ppa['pending_with']);
        $this->assertStringContainsString('Alice', (string) $ppa['pending_with']);
        $this->assertStringContainsString('/performance/form/ppa/', (string) $ppa['form_url']);

        $midterm = $rows[1];
        $this->assertSame('midterm', $midterm['phase']);
        $this->assertNotEmpty($midterm['pending_with']);

        $endterm = $rows[2];
        $this->assertSame('endterm', $endterm['phase']);
        $this->assertNull($endterm['pending_with']);
        $this->assertSame('Draft', $endterm['status']);
    }

    public function test_approval_history_paginates(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $entryId = 'ppa-entry-page-'.$i;
            $this->insertPpaEntry([
                'entry_id' => $entryId,
                'staff_id' => 100,
                'supervisor_id' => 50,
                'supervisor2_id' => 51,
                'performance_period' => 'January-202'.$i.'-to-December-202'.$i,
                'draft_status' => 2,
                'staff_sign_off' => 1,
            ]);
            DB::table('ppa_approval_trail')->insert([
                'entry_id' => $entryId,
                'staff_id' => 50,
                'comments' => 'ok',
                'action' => 'Approved',
                'created_at' => sprintf('2026-03-%02d 10:00:00', $i),
            ]);
        }

        session()->put($this->portalSession(50, permissions: [74]));

        $response = app(PerformanceHubApiController::class)->approvalHistory(
            Request::create('/api/v1/performance/approval-history', 'GET', [
                'page' => 1,
                'per_page' => 2,
            ]),
            app(PerformanceApprovalService::class),
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(5, (int) $payload['meta']['total']);
        $this->assertSame(3, (int) $payload['meta']['last_page']);
        $this->assertSame(2, (int) $payload['meta']['per_page']);
        $this->assertCount(2, $payload['data']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function portalSession(int $staffId, int $roleId = 17, array $permissions = [74]): array
    {
        return [
            'user' => [
                'staff_id' => $staffId,
                'role_id' => $roleId,
                'permissions' => $permissions,
            ],
        ];
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('SAPNO')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('photo')->nullable();
            $table->date('initiation_date')->nullable();
            $table->string('work_email')->nullable();
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->integer('staff_id');
            $table->integer('job_id')->nullable();
            $table->integer('job_acting_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('funder_id')->nullable();
            $table->integer('contract_type_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->integer('job_id')->primary();
            $table->string('job_name')->nullable();
        });

        Schema::create('jobs_acting', function (Blueprint $table): void {
            $table->integer('job_acting_id')->primary();
            $table->string('job_acting')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->integer('division_id')->primary();
            $table->string('division_name')->nullable();
        });

        Schema::create('funders', function (Blueprint $table): void {
            $table->integer('funder_id')->primary();
            $table->string('funder')->nullable();
        });

        Schema::create('contract_types', function (Blueprint $table): void {
            $table->integer('contract_type_id')->primary();
            $table->string('contract_type')->nullable();
        });

        Schema::create('ppa_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('allow_supervisor_return')->default(1);
            $table->integer('allow_supervisor_comments')->default(1);
            $table->integer('allow_supervisor_ppa_edit')->default(1);
            $table->integer('allow_employee_comments')->default(1);
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
            $table->longText('objectives')->nullable();
            $table->string('training_recommended', 3)->default('No');
            $table->longText('required_skills')->nullable();
            $table->text('training_contributions')->nullable();
            $table->text('recommended_trainings')->nullable();
            $table->text('recommended_trainings_details')->nullable();
            $table->boolean('staff_sign_off')->default(false);
            $table->tinyInteger('draft_status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->longText('midterm_objectives')->nullable();
            $table->longText('midterm_competency')->nullable();
            $table->text('midterm_achievements')->nullable();
            $table->text('midterm_non_achievements')->nullable();
            $table->text('midterm_comments')->nullable();
            $table->text('midterm_training_review')->nullable();
            $table->longText('midterm_recommended_skills')->nullable();
            $table->longText('midterm_training_contributions')->nullable();
            $table->longText('midterm_recommended_trainings')->nullable();
            $table->longText('midterm_recommended_trainings_details')->nullable();
            $table->integer('midterm_rating_by')->nullable();
            $table->boolean('midterm_sign_off')->default(false);
            $table->tinyInteger('midterm_draft_status')->default(1);
            $table->dateTime('midterm_created_at')->nullable();
            $table->dateTime('midterm_updated_at')->nullable();
            $table->integer('midterm_supervisor_1')->nullable();
            $table->integer('midterm_supervisor_2')->nullable();
            $table->longText('endterm_objectives')->nullable();
            $table->longText('endterm_competency')->nullable();
            $table->text('endterm_achievements')->nullable();
            $table->text('endterm_non_achievements')->nullable();
            $table->text('endterm_comments')->nullable();
            $table->text('endterm_training_review')->nullable();
            $table->longText('endterm_recommended_skills')->nullable();
            $table->longText('endterm_training_contributions')->nullable();
            $table->longText('endterm_recommended_trainings')->nullable();
            $table->longText('endterm_recommended_trainings_details')->nullable();
            $table->integer('endterm_rating_by')->nullable();
            $table->boolean('endterm_sign_off')->default(false);
            $table->tinyInteger('endterm_draft_status')->default(1);
            $table->integer('endterm_supervisor_1')->nullable();
            $table->integer('endterm_supervisor_2')->nullable();
            $table->dateTime('endterm_created_at')->nullable();
            $table->dateTime('endterm_updated_at')->nullable();
            $table->boolean('endterm_supervisor1_discussion_confirmed')->default(false);
            $table->boolean('endterm_staff_discussion_confirmed')->default(false);
            $table->boolean('endterm_staff_rating_acceptance')->nullable();
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

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            [
                'staff_id' => 50,
                'SAPNO' => null,
                'fname' => 'Alice',
                'lname' => 'Supervisor',
                'initiation_date' => null,
                'work_email' => 'alice.supervisor@example.test',
            ],
            [
                'staff_id' => 51,
                'SAPNO' => null,
                'fname' => 'Bob',
                'lname' => 'Supervisor',
                'initiation_date' => null,
                'work_email' => 'bob.supervisor@example.test',
            ],
            [
                'staff_id' => 100,
                'fname' => 'Current',
                'lname' => 'Staff',
                'SAPNO' => 'AU-100',
                'initiation_date' => '2024-01-01',
                'work_email' => 'current.staff@example.test',
            ],
        ]);

        DB::table('jobs')->insert(['job_id' => 1, 'job_name' => 'Advisor']);
        DB::table('jobs_acting')->insert(['job_acting_id' => 1, 'job_acting' => 'Acting Advisor']);
        DB::table('divisions')->insert(['division_id' => 1, 'division_name' => 'People']);
        DB::table('funders')->insert(['funder_id' => 1, 'funder' => 'AU']);
        DB::table('contract_types')->insert(['contract_type_id' => 1, 'contract_type' => 'Permanent']);
        DB::table('staff_contracts')->insert([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'job_id' => 1,
            'job_acting_id' => 1,
            'division_id' => 1,
            'funder_id' => 1,
            'contract_type_id' => 1,
            'status_id' => 1,
            'first_supervisor' => 50,
            'second_supervisor' => 51,
        ]);
        DB::table('ppa_configs')->insert([
            'id' => 1,
            'allow_supervisor_return' => 1,
            'allow_supervisor_comments' => 1,
            'allow_supervisor_ppa_edit' => 1,
            'allow_employee_comments' => 1,
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
    protected function insertPpaEntry(array $attributes): void
    {
        DB::table('ppa_entries')->insert(array_merge([
            'staff_id' => 100,
            'staff_contract_id' => 1000,
            'performance_period' => 'January-2026-to-December-2026',
            'entry_id' => 'ppa-entry-default',
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'objectives' => json_encode([]),
            'training_recommended' => 'No',
            'required_skills' => json_encode([]),
            'staff_sign_off' => 1,
            'draft_status' => 1,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $attributes));
    }
}
