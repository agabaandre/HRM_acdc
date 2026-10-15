<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PerformanceWorkflowCorrectionService;
use Modules\Performance\Services\PerformanceWorkflowService;
use Tests\TestCase;

class PerformanceWorkflowCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        $this->createTables();
        $this->seedFixtures();
    }

    public function test_preview_marks_ppa_correctable_after_first_supervisor_when_second_is_off(): void
    {
        $this->insertSubmittedPpa();
        $this->insertTrail('ppa-entry-1', 50, 'Approved');

        $preview = app(PerformanceWorkflowCorrectionService::class)->preview('ppa-entry-1');

        $this->assertTrue($preview['can_correct']);
        $this->assertTrue($preview['phases']['ppa']['can_correct']);
        $this->assertSame('Approved', $preview['phases']['ppa']['state']);
        $this->assertSame(0, $preview['phases']['ppa']['draft_status']);
        $this->assertFalse($preview['phases']['ppa']['requires_second_supervisor']);
        $this->assertFalse($preview['phases']['endterm']['can_correct']);
    }

    public function test_apply_sets_ppa_and_midterm_draft_status_without_touching_endterm(): void
    {
        $this->insertSubmittedPpa([
            'midterm_created_at' => now()->toDateTimeString(),
            'midterm_draft_status' => 0,
            'midterm_supervisor_1' => 50,
            'midterm_supervisor_2' => 51,
            'endterm_created_at' => now()->toDateTimeString(),
            'endterm_draft_status' => 0,
            'endterm_supervisor_1' => 50,
            'endterm_supervisor_2' => 51,
            'endterm_staff_consent_at' => now()->toDateTimeString(),
        ]);
        $this->insertTrail('ppa-entry-1', 50, 'Approved');
        $this->insertTrail('ppa-entry-1', 50, 'Approved', 'ppa_approval_trail_midterm');
        $this->insertTrail('ppa-entry-1', 50, 'Approved', 'ppa_approval_trail_end_term');

        $result = app(PerformanceWorkflowCorrectionService::class)->apply('ppa-entry-1');

        $this->assertSame(['ppa', 'midterm'], $result['corrected_phases']);
        $this->assertSame(2, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->value('draft_status'));
        $this->assertSame(2, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->value('midterm_draft_status'));
        $this->assertSame(0, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->value('endterm_draft_status'));
        $this->assertFalse($result['can_correct']);
    }

    public function test_does_not_correct_when_ppa_second_supervisor_is_required(): void
    {
        DB::table('ppa_configs')->update(['ppa_requires_second_supervisor' => 1]);
        $this->insertSubmittedPpa();
        $this->insertTrail('ppa-entry-1', 50, 'Approved');

        $result = app(PerformanceWorkflowCorrectionService::class)->apply('ppa-entry-1');

        $this->assertSame([], $result['corrected_phases']);
        $this->assertSame(0, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->value('draft_status'));
        $this->assertSame('Pending second supervisor', $result['phases']['ppa']['state']);
    }

    public function test_finalize_if_ready_is_idempotent_once_approved(): void
    {
        $this->insertSubmittedPpa();
        $this->insertTrail('ppa-entry-1', 50, 'Approved');
        $service = app(PerformanceWorkflowCorrectionService::class);

        $this->assertTrue($service->finalizeIfReady(
            DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->first(),
            PerformancePhase::Ppa,
        ));
        $this->assertFalse($service->finalizeIfReady(
            DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->first(),
            PerformancePhase::Ppa,
        ));
    }

    public function test_endterm_requires_employee_consent_before_second_supervisor(): void
    {
        $this->insertSubmittedPpa([
            'endterm_created_at' => now()->toDateTimeString(),
            'endterm_draft_status' => 0,
            'endterm_supervisor_1' => 50,
            'endterm_supervisor_2' => 51,
        ]);
        $this->insertTrail('ppa-entry-1', 50, 'Approved', 'ppa_approval_trail_end_term');

        $entry = DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->first();
        $workflow = app(PerformanceWorkflowService::class);
        $state = $workflow->resolveState($entry, PerformancePhase::Endterm);

        $this->assertSame('employee_consent', $state['step']);
        $this->assertSame(100, $state['actor_staff_id']);
        $this->assertFalse($workflow->canActorApprove($entry, PerformancePhase::Endterm, 51));
        $this->assertTrue($workflow->canActorApprove($entry, PerformancePhase::Endterm, 100));

        DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->update([
            'endterm_staff_consent_at' => now()->toDateTimeString(),
        ]);
        $entry = DB::table('ppa_entries')->where('entry_id', 'ppa-entry-1')->first();
        $state = $workflow->resolveState($entry, PerformancePhase::Endterm);

        $this->assertSame('supervisor_2', $state['step']);
        $this->assertSame(51, $state['actor_staff_id']);
        $this->assertTrue($workflow->canActorApprove($entry, PerformancePhase::Endterm, 51));
        $this->assertFalse($workflow->canActorApprove($entry, PerformancePhase::Endterm, 100));
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
        });

        Schema::create('ppa_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('ppa_requires_second_supervisor')->default(false);
            $table->boolean('midterm_requires_second_supervisor')->default(false);
            $table->boolean('endterm_requires_second_supervisor')->default(true);
            $table->boolean('endterm_requires_employee_consent')->default(true);
        });

        Schema::create('ppa_entries', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id');
            $table->string('performance_period', 50);
            $table->string('entry_id', 100)->unique();
            $table->integer('supervisor_id')->nullable();
            $table->integer('supervisor2_id')->nullable();
            $table->tinyInteger('draft_status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->tinyInteger('midterm_draft_status')->default(1);
            $table->dateTime('midterm_created_at')->nullable();
            $table->dateTime('midterm_updated_at')->nullable();
            $table->integer('midterm_supervisor_1')->nullable();
            $table->integer('midterm_supervisor_2')->nullable();
            $table->tinyInteger('endterm_draft_status')->default(1);
            $table->integer('endterm_supervisor_1')->nullable();
            $table->integer('endterm_supervisor_2')->nullable();
            $table->dateTime('endterm_created_at')->nullable();
            $table->dateTime('endterm_updated_at')->nullable();
            $table->dateTime('endterm_staff_consent_at')->nullable();
        });

        foreach (['ppa_approval_trail', 'ppa_approval_trail_midterm', 'ppa_approval_trail_end_term'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->increments('id');
                $blueprint->string('entry_id', 100);
                $blueprint->integer('staff_id');
                $blueprint->text('comments')->nullable();
                $blueprint->string('action');
                $blueprint->dateTime('created_at')->nullable();
            });
        }
    }

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            ['staff_id' => 50, 'fname' => 'First', 'lname' => 'Supervisor'],
            ['staff_id' => 51, 'fname' => 'Second', 'lname' => 'Supervisor'],
            ['staff_id' => 100, 'fname' => 'Pat', 'lname' => 'Staff'],
        ]);
        DB::table('ppa_configs')->insert([
            'id' => 1,
            'ppa_requires_second_supervisor' => 0,
            'midterm_requires_second_supervisor' => 0,
            'endterm_requires_second_supervisor' => 1,
            'endterm_requires_employee_consent' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertSubmittedPpa(array $attributes = []): void
    {
        DB::table('ppa_entries')->insert(array_merge([
            'staff_id' => 100,
            'performance_period' => 'January-2026-to-December-2026',
            'entry_id' => 'ppa-entry-1',
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $attributes));
    }

    protected function insertTrail(string $entryId, int $staffId, string $action, string $table = 'ppa_approval_trail'): void
    {
        DB::table($table)->insert([
            'entry_id' => $entryId,
            'staff_id' => $staffId,
            'action' => $action,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
