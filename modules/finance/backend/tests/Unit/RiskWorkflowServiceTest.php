<?php

namespace Tests\Unit;

use App\Services\RiskWorkflowService;
use App\Services\StaffPortalOrgClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskWorkflowServiceTest extends TestCase
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
        $this->seedDefaultWorkflow();
    }

    public function test_director_skipped_when_no_director(): void
    {
        $riskId = $this->insertRisk(35);
        $svc = $this->makeService([
            'divisions' => [[
                'division_id' => 35,
                'division_head' => 10,
                'risk_focal_person' => 5,
                'director_id' => null,
                'directorate_id' => null,
            ]],
            'directorates' => [],
        ]);

        $svc->start($riskId);

        $statuses = DB::table('rr_risk_approvals')->where('risk_id', $riskId)->orderBy('step_order')->get();
        $director = $statuses->firstWhere('role', 'director');
        $this->assertNotNull($director);
        $this->assertSame('skipped', $director->status);

        $pending = $statuses->firstWhere('status', 'pending');
        $this->assertSame('risk_focal', $pending->role);
    }

    public function test_feedback_rejects_peer_or_higher(): void
    {
        $riskId = $this->insertRisk(35);
        $svc = $this->makeService([
            'divisions' => [[
                'division_id' => 35,
                'division_head' => 10,
                'risk_focal_person' => 5,
                'director_id' => 20,
                'directorate_id' => 1,
            ]],
            'directorates' => [],
        ]);
        $svc->start($riskId);

        // Advance to HOD pending
        $focal = DB::table('rr_risk_approvals')->where('risk_id', $riskId)->where('role', 'risk_focal')->first();
        $svc->approve((int) $focal->id, 5);

        $hod = DB::table('rr_risk_approvals')->where('risk_id', $riskId)->where('role', 'hod')->where('status', 'pending')->first();
        $this->assertNotNull($hod);

        $this->expectException(\InvalidArgumentException::class);
        $svc->requestFeedback((int) $hod->id, 10, [20], 'Need director input'); // director is higher
    }

    public function test_approve_advances_to_signed_off(): void
    {
        $riskId = $this->insertRisk(35);
        $svc = $this->makeService([
            'divisions' => [[
                'division_id' => 35,
                'division_head' => 10,
                'risk_focal_person' => 5,
                'director_id' => null,
                'directorate_id' => null,
            ]],
            'directorates' => [],
        ]);
        $svc->start($riskId);

        foreach (['risk_focal' => 5, 'hod' => 10, 'sm_focal' => 99, 'extra' => 100] as $role => $actor) {
            $row = DB::table('rr_risk_approvals')
                ->where('risk_id', $riskId)
                ->where('role', $role)
                ->where('status', 'pending')
                ->first();
            if (! $row && $role === 'director') {
                continue;
            }
            $this->assertNotNull($row, "pending {$role}");
            $svc->approve((int) $row->id, $actor);
        }

        $risk = DB::table('rr_risks')->where('id', $riskId)->first();
        $this->assertSame('signed_off', $risk->workflow_state);
    }

    public function test_ensure_division_workflows_creates_once(): void
    {
        $svc = $this->makeService(['divisions' => [], 'directorates' => []]);
        $created = $svc->ensureDivisionWorkflows([
            [
                'division_id' => 35,
                'division_short_name' => 'OIO',
                'division_name' => 'Internal Oversight',
            ],
            [
                'division_id' => 40,
                'division_short_name' => 'PHC',
                'division_name' => 'Public Health',
            ],
        ]);

        $this->assertCount(2, $created);
        $this->assertTrue($created[0]['created']);
        $this->assertTrue($created[1]['created']);

        $again = $svc->ensureDivisionWorkflows([
            ['division_id' => 35, 'division_short_name' => 'OIO', 'division_name' => 'Internal Oversight'],
        ]);
        $this->assertFalse($again[0]['created']);
        $this->assertSame($created[0]['workflow_id'], $again[0]['workflow_id']);
        $this->assertSame(1, DB::table('rr_approval_workflows')->where('division_id', 35)->count());
    }

    public function test_update_workflow_sets_sm_and_extra(): void
    {
        $svc = $this->makeService(['divisions' => [], 'directorates' => []]);
        $svc->ensureDivisionWorkflows([
            ['division_id' => 35, 'division_short_name' => 'OIO', 'division_name' => 'OIO'],
        ]);
        $wid = $svc->divisionWorkflowId(35);
        $this->assertNotNull($wid);

        $row = $svc->updateWorkflow((int) $wid, 'OIO custom', [
            ['step_order' => 1, 'role' => 'risk_focal', 'staff_id' => null, 'skippable_if_empty' => false],
            ['step_order' => 2, 'role' => 'hod', 'staff_id' => null, 'skippable_if_empty' => false],
            ['step_order' => 3, 'role' => 'director', 'staff_id' => null, 'skippable_if_empty' => true],
            ['step_order' => 4, 'role' => 'sm_focal', 'staff_id' => 501, 'skippable_if_empty' => false],
            ['step_order' => 5, 'role' => 'extra', 'staff_id' => 502, 'skippable_if_empty' => false],
        ]);

        $this->assertSame('OIO custom', $row['name']);
        $sm = collect($row['steps'])->firstWhere('role', 'sm_focal');
        $extra = collect($row['steps'])->firstWhere('role', 'extra');
        $this->assertSame(501, (int) $sm['staff_id']);
        $this->assertSame(502, (int) $extra['staff_id']);
    }

    /**
     * @param  array{divisions: list<array<string,mixed>>, directorates: list<array<string,mixed>>}  $org
     */
    private function makeService(array $org): RiskWorkflowService
    {
        $client = $this->createMock(StaffPortalOrgClient::class);
        $client->method('fetchOrg')->willReturn($org);

        return new RiskWorkflowService($client);
    }

    private function insertRisk(int $divisionId): int
    {
        return (int) DB::table('rr_risks')->insertGetId([
            'name' => 'Workflow test risk',
            'division_id' => $divisionId,
            'workflow_state' => 'draft',
            'imported' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedDefaultWorkflow(): void
    {
        $wid = DB::table('rr_approval_workflows')->insertGetId([
            'division_id' => null,
            'directorate_id' => null,
            'name' => 'Global default',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $steps = [
            [1, 'risk_focal', null, false],
            [2, 'hod', null, false],
            [3, 'director', null, true],
            [4, 'sm_focal', 99, false],
            [5, 'extra', 100, false],
        ];
        foreach ($steps as [$order, $role, $staff, $skip]) {
            DB::table('rr_approval_workflow_steps')->insert([
                'workflow_id' => $wid,
                'step_order' => $order,
                'role' => $role,
                'staff_id' => $staff,
                'skippable_if_empty' => $skip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createTables(): void
    {
        Schema::create('rr_risks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('directorate_id')->nullable();
            $table->string('workflow_state', 64)->default('draft');
            $table->boolean('imported')->default(false);
            $table->timestamps();
        });
        Schema::create('rr_approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('directorate_id')->nullable();
            $table->string('name', 128)->default('Default');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::create('rr_approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedSmallInteger('step_order');
            $table->string('role', 32);
            $table->unsignedInteger('staff_id')->nullable();
            $table->boolean('skippable_if_empty')->default(false);
            $table->timestamps();
        });
        Schema::create('rr_risk_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_id');
            $table->unsignedBigInteger('workflow_step_id');
            $table->unsignedSmallInteger('step_order');
            $table->string('role', 32);
            $table->unsignedInteger('assignee_staff_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('acted_by_staff_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('rr_feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_approval_id');
            $table->unsignedInteger('requester_staff_id');
            $table->text('message');
            $table->string('status', 32)->default('open');
            $table->timestamps();
        });
        Schema::create('rr_feedback_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedback_request_id');
            $table->unsignedInteger('staff_id');
            $table->timestamps();
        });
        Schema::create('rr_feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedback_request_id');
            $table->unsignedInteger('responder_staff_id');
            $table->text('message');
            $table->timestamps();
        });
    }
}
