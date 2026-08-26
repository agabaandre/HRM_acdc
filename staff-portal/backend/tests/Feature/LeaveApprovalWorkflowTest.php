<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Models\StaffLeaveApprovalStep;
use Modules\Leave\Models\StaffLeaveCompensatoryCredit;
use Modules\Leave\Services\LeaveApprovalWorkflowService;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Leave\Services\LeaveRequestService;
use Tests\TestCase;

class LeaveApprovalWorkflowTest extends TestCase
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

        Carbon::setTestNow(Carbon::parse('2026-08-27 09:00:00'));
        $this->createTables();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_definition_always_keeps_hod_first_and_rejects_hr_without_staff(): void
    {
        $workflow = app(LeaveApprovalWorkflowService::class);

        $saved = $workflow->saveDefinition(true, [
            ['role' => 'hr', 'staff_id' => 4, 'label' => 'HR Officer'],
            ['role' => 'hr', 'staff_id' => 5, 'label' => 'Head of HR'],
        ]);

        $this->assertTrue($saved['enabled']);
        $this->assertSame('hod', $saved['levels'][0]['role']);
        $this->assertNull($saved['levels'][0]['staff_id']);
        $this->assertSame('Head of Division', $saved['levels'][0]['label']);
        $this->assertSame(4, $saved['levels'][1]['staff_id']);
        $this->assertSame(5, $saved['levels'][2]['staff_id']);
        $this->assertTrue($saved['levels'][0]['locked']);

        $this->expectException(\InvalidArgumentException::class);
        $workflow->saveDefinition(true, [
            ['role' => 'hr', 'staff_id' => null, 'label' => 'HR Officer'],
        ]);
    }

    public function test_default_hod_comes_from_the_employee_division_head(): void
    {
        $hod = app(LeaveApprovalWorkflowService::class)->defaultHodForStaff(1);

        $this->assertSame(3, $hod['staff_id']);
        $this->assertStringContainsString('Dana', $hod['name']);
    }

    public function test_submit_snapshots_hod_then_hr_series_and_allows_hod_override(): void
    {
        $this->enableWorkflow();

        $leave = app(LeaveRequestService::class)->submit([
            'staff_id' => 1,
            'leave_id' => 1,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'requested_days' => 2,
            'email_leave' => 'a@b.c',
            'mobile_leave' => '1',
            'supporting_staff' => '2',
            'division_head' => 2,
        ]);

        $this->assertSame(2, (int) $leave->division_head);

        $steps = StaffLeaveApprovalStep::query()
            ->where('request_id', $leave->request_id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $steps);
        $this->assertSame('hod', $steps[0]->role);
        $this->assertSame(2, (int) $steps[0]->staff_id);
        $this->assertSame('hr', $steps[1]->role);
        $this->assertSame(4, (int) $steps[1]->staff_id);
        $this->assertSame(5, (int) $steps[2]->staff_id);
        $this->assertSame('Pending', $steps[0]->status);
    }

    public function test_hr_cannot_approve_before_hod_and_full_chain_approves_overall(): void
    {
        $this->enableWorkflow();
        $leave = $this->submitLeave();
        $workflow = app(LeaveApprovalWorkflowService::class);

        try {
            $workflow->decide((int) $leave->request_id, 4, 'approve');
            $this->fail('HR must not skip the HOD step.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('current', strtolower($e->getMessage()));
        }

        $workflow->decide((int) $leave->request_id, 3, 'approve', 'HOD ok');
        $leave->refresh();
        $this->assertSame('Pending', $leave->overall_status);
        $this->assertSame('Approved', $leave->approval_status3);

        $workflow->decide((int) $leave->request_id, 4, 'approve');
        $leave->refresh();
        $this->assertSame('Pending', $leave->overall_status);

        $workflow->decide((int) $leave->request_id, 5, 'approve');
        $leave->refresh();
        $this->assertSame('Approved', $leave->overall_status);
    }

    public function test_hod_reject_sets_overall_rejected_and_blocks_later_steps(): void
    {
        $this->enableWorkflow();
        $leave = $this->submitLeave();
        $workflow = app(LeaveApprovalWorkflowService::class);

        $workflow->decide((int) $leave->request_id, 3, 'reject', 'Not now');
        $leave->refresh();
        $this->assertSame('Rejected', $leave->overall_status);

        $this->expectException(\InvalidArgumentException::class);
        $workflow->decide((int) $leave->request_id, 4, 'approve');
    }

    public function test_disabled_workflow_does_not_snapshot_and_hod_still_finalizes(): void
    {
        app(LeavePolicyService::class)->save(['approval_workflow_enabled' => false]);

        $leave = $this->submitLeave();
        $this->assertSame(0, StaffLeaveApprovalStep::query()->where('request_id', $leave->request_id)->count());

        app(LeaveRequestService::class)->approve((int) $leave->request_id, 'hod', 'Approved');
        $leave->refresh();
        $this->assertSame('Approved', $leave->overall_status);
    }

    public function test_workflow_approval_consumes_holiday_credits(): void
    {
        $this->enableWorkflow();
        StaffLeaveCompensatoryCredit::query()->create([
            'staff_id' => 1,
            'kind' => 'holiday',
            'days' => 2,
            'days_used' => 0,
            'granted_on' => '2026-08-01',
            'expires_on' => '2026-12-31',
            'source_date' => '2026-08-01',
        ]);

        $leave = app(LeaveRequestService::class)->submit([
            'staff_id' => 1,
            'leave_id' => 2,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'requested_days' => 1,
            'email_leave' => 'a@b.c',
            'mobile_leave' => '1',
            'supporting_staff' => '2',
            'division_head' => 3,
        ]);

        $workflow = app(LeaveApprovalWorkflowService::class);
        $workflow->decide((int) $leave->request_id, 3, 'approve');
        $workflow->decide((int) $leave->request_id, 4, 'approve');
        $workflow->decide((int) $leave->request_id, 5, 'approve');

        $credit = StaffLeaveCompensatoryCredit::query()->where('staff_id', 1)->where('kind', 'holiday')->first();
        $this->assertSame(1.0, (float) $credit->days_used);
    }

    protected function enableWorkflow(): void
    {
        app(LeaveApprovalWorkflowService::class)->saveDefinition(true, [
            ['role' => 'hod', 'label' => 'Head of Division'],
            ['role' => 'hr', 'staff_id' => 4, 'label' => 'HR Officer'],
            ['role' => 'hr', 'staff_id' => 5, 'label' => 'Head of HR'],
        ]);
    }

    protected function submitLeave(): StaffLeave
    {
        return app(LeaveRequestService::class)->submit([
            'staff_id' => 1,
            'leave_id' => 1,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'requested_days' => 2,
            'email_leave' => 'a@b.c',
            'mobile_leave' => '1',
            'supporting_staff' => '2',
        ]);
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('title')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('oname')->nullable();
            $table->string('work_email')->nullable();
            $table->string('SAPNO')->nullable();
        });
        Schema::create('divisions', function (Blueprint $table): void {
            $table->integer('division_id')->primary();
            $table->string('division_name')->nullable();
            $table->integer('division_head')->nullable();
        });
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->integer('staff_contract_id')->primary();
            $table->integer('staff_id');
            $table->integer('division_id')->nullable();
            $table->integer('duty_station_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
        });
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->increments('leave_id');
            $table->string('leave_name');
            $table->string('code', 40)->nullable();
            $table->integer('leave_days')->default(0);
            $table->integer('is_accrued')->default(0);
            $table->float('accrual_rate')->default(0);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('leave_policy_settings', function (Blueprint $table): void {
            $table->string('setting_key', 80)->primary();
            $table->json('setting_value');
            $table->timestamps();
        });
        Schema::create('staff_leave', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->integer('staff_id');
            $table->date('start_date');
            $table->integer('leave_id');
            $table->date('end_date');
            $table->string('email_leave')->nullable();
            $table->string('mobile_leave')->nullable();
            $table->string('supporting_staff')->nullable();
            $table->integer('requested_days')->default(0);
            $table->integer('leave_balance')->default(0);
            $table->text('remarks')->nullable();
            $table->integer('contract_id')->default(0);
            $table->integer('supervisor_id')->default(0);
            $table->integer('supervisor2_id')->default(0);
            $table->integer('division_head')->default(0);
            $table->text('reject_reason')->nullable();
            $table->text('supporting_documentation')->nullable();
            $table->string('approval_status')->default('Pending');
            $table->string('approval_status1')->default('Pending');
            $table->string('approval_status2')->default('Pending');
            $table->string('approval_status3')->default('Pending');
            $table->string('overall_status')->default('Pending');
            $table->timestamps();
        });
        Schema::create('staff_leave_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('leave_id');
            $table->unsignedSmallInteger('calendar_year');
            $table->decimal('opening_days', 8, 2)->default(0);
            $table->decimal('carried_forward_days', 8, 2)->default(0);
            $table->decimal('compensatory_days', 8, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('staff_leave_compensatory_credits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('staff_id');
            $table->string('kind', 20)->default('other');
            $table->decimal('days', 8, 2);
            $table->decimal('days_used', 8, 2)->default(0);
            $table->string('reason', 500)->nullable();
            $table->date('granted_on');
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('granted_by_user_id')->nullable();
            $table->unsignedInteger('source_holiday_rule_id')->nullable();
            $table->date('source_date')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_approval_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('role', 20);
            $table->unsignedInteger('staff_id')->nullable();
            $table->string('label', 120);
            $table->timestamps();
        });
        Schema::create('staff_leave_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('role', 20);
            $table->unsignedInteger('staff_id')->default(0);
            $table->string('label', 120);
            $table->string('status', 20)->default('Pending');
            $table->text('comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedInteger('acted_by')->nullable();
            $table->timestamps();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            ['staff_id' => 1, 'fname' => 'Ada', 'lname' => 'Employee', 'work_email' => 'ada@example.com'],
            ['staff_id' => 2, 'fname' => 'Bea', 'lname' => 'Cover', 'work_email' => 'bea@example.com'],
            ['staff_id' => 3, 'fname' => 'Dana', 'lname' => 'Head', 'work_email' => 'dana@example.com'],
            ['staff_id' => 4, 'fname' => 'Eve', 'lname' => 'Hrone', 'work_email' => 'eve@example.com'],
            ['staff_id' => 5, 'fname' => 'Fay', 'lname' => 'Hrtwo', 'work_email' => 'fay@example.com'],
        ]);
        DB::table('divisions')->insert([
            ['division_id' => 10, 'division_name' => 'Surveillance', 'division_head' => 3],
        ]);
        DB::table('staff_contracts')->insert([
            [
                'staff_contract_id' => 1,
                'staff_id' => 1,
                'division_id' => 10,
                'duty_station_id' => 1,
                'status_id' => 1,
                'first_supervisor' => 2,
                'second_supervisor' => 0,
            ],
        ]);
        LeaveType::query()->create([
            'leave_name' => 'Annual leave',
            'code' => 'ANNUAL',
            'leave_days' => 20,
            'is_accrued' => 0,
            'is_active' => true,
        ]);
        LeaveType::query()->create([
            'leave_name' => 'Holiday compensatory',
            'code' => 'HOLIDAY_COMPENSATORY',
            'leave_days' => 0,
            'is_accrued' => 0,
            'is_active' => true,
        ]);
    }
}
