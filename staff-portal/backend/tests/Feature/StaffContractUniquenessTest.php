<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use Modules\Staff\Services\StaffContractService;
use Tests\TestCase;

class StaffContractUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedStaff(100);
    }

    public function test_creating_a_new_current_contract_demotes_the_previous_current_contract(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]);

        $newContractId = app(StaffContractService::class)->create(100, $this->contractForm([
            'status_id' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2027-08-01',
        ]));

        $statuses = \DB::table('staff_contracts')
            ->where('staff_id', 100)
            ->orderBy('staff_contract_id')
            ->pluck('status_id', 'staff_contract_id')
            ->map(fn ($status) => (int) $status)
            ->all();

        $this->assertNotNull($newContractId);
        $this->assertSame(6, $statuses[1000]);
        $this->assertSame(1, $statuses[$newContractId]);
        $this->assertCount(1, array_filter($statuses, fn (int $status): bool => in_array($status, StaffContractService::CURRENT_STATUSES, true)));
    }

    public function test_creating_a_new_current_contract_demotes_latest_prior_and_other_stray_current_rows(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 2,
            'start_date' => '2025-01-01',
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->insertContract([
            'staff_contract_id' => 1001,
            'staff_id' => 100,
            'status_id' => 1,
            'start_date' => '2026-01-01',
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $newContractId = app(StaffContractService::class)->create(100, $this->contractForm([
            'status_id' => 1,
            'start_date' => '2026-08-01',
            'end_date' => now()->addYears(2)->toDateString(),
        ]));

        $statuses = \DB::table('staff_contracts')
            ->where('staff_id', 100)
            ->orderBy('staff_contract_id')
            ->pluck('status_id', 'staff_contract_id')
            ->map(fn ($status) => (int) $status)
            ->all();

        $this->assertNotNull($newContractId);
        $this->assertSame(6, $statuses[1000]);
        $this->assertSame(6, $statuses[1001]);
        $this->assertSame(1, $statuses[$newContractId]);
    }

    public function test_creating_a_new_current_contract_leaves_expired_latest_prior_unchanged(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 1,
            'start_date' => '2025-01-01',
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $this->insertContract([
            'staff_contract_id' => 1001,
            'staff_id' => 100,
            'status_id' => 3,
            'start_date' => '2026-01-01',
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $newContractId = app(StaffContractService::class)->create(100, $this->contractForm([
            'status_id' => 1,
            'start_date' => '2026-08-01',
            'end_date' => now()->addYears(2)->toDateString(),
        ]));

        $statuses = \DB::table('staff_contracts')
            ->where('staff_id', 100)
            ->orderBy('staff_contract_id')
            ->pluck('status_id', 'staff_contract_id')
            ->map(fn ($status) => (int) $status)
            ->all();

        $this->assertNotNull($newContractId);
        $this->assertSame(6, $statuses[1000]);
        $this->assertSame(3, $statuses[1001]);
        $this->assertSame(1, $statuses[$newContractId]);
    }

    public function test_updating_a_contract_to_current_fails_when_another_current_contract_exists(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 6,
            'start_date' => '2025-01-01',
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $this->insertContract([
            'staff_contract_id' => 1001,
            'staff_id' => 100,
            'status_id' => 1,
            'start_date' => '2026-01-01',
            'end_date' => now()->addYears(2)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        app(StaffContractService::class)->update(1000, 100, $this->contractForm([
            'status_id' => 1,
            'start_date' => '2025-01-01',
            'end_date' => now()->addYear()->toDateString(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function contractForm(array $overrides = []): array
    {
        return array_merge([
            'job_id' => 1,
            'job_acting_id' => '',
            'grade_id' => 'P1',
            'contracting_institution_id' => 1,
            'funder_id' => 1,
            'first_supervisor' => 1,
            'second_supervisor' => '',
            'contract_type_id' => 1,
            'duty_station_id' => 1,
            'division_id' => 1,
            'unit_id' => 1,
            'other_associated_divisions' => [],
            'start_date' => '2026-01-01',
            'end_date' => now()->addYear()->toDateString(),
            'status_id' => 1,
            'comments' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertContract(array $attributes): void
    {
        \DB::table('staff_contracts')->insert(array_merge([
            'job_id' => 1,
            'job_acting_id' => null,
            'grade_id' => 'P1',
            'contracting_institution_id' => 1,
            'funder_id' => 1,
            'first_supervisor' => 1,
            'second_supervisor' => null,
            'contract_type_id' => 1,
            'duty_station_id' => 1,
            'division_id' => 1,
            'unit_id' => 1,
            'other_associated_divisions' => null,
            'comments' => '',
            'file_name' => null,
        ], $attributes));
    }

    protected function seedStaff(int $staffId): void
    {
        \DB::table('staff')->insert([
            'staff_id' => $staffId,
            'fname' => 'Alice',
            'lname' => 'Example',
            'work_email' => null,
            'flag' => 0,
            'email_disabled_by' => 0,
            'email_status' => 0,
            'email_disabled_at' => null,
        ]);
    }

    protected function createTables(): void
    {
        \Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
            $table->integer('flag')->default(0);
            $table->integer('email_disabled_by')->default(0);
            $table->integer('email_status')->default(0);
            $table->timestamp('email_disabled_at')->nullable();
        });

        \Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->integer('staff_contract_id')->primary();
            $table->integer('staff_id');
            $table->integer('job_id')->nullable();
            $table->integer('job_acting_id')->nullable();
            $table->string('grade_id')->nullable();
            $table->integer('contracting_institution_id')->nullable();
            $table->integer('funder_id')->nullable();
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
            $table->integer('contract_type_id')->nullable();
            $table->integer('duty_station_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->text('other_associated_divisions')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('status_id')->nullable();
            $table->text('comments')->nullable();
            $table->string('file_name')->nullable();
        });

        \Schema::create('user', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('auth_staff_id')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(0);
        });

        \Schema::create('setting', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('default_password')->nullable();
        });

        \Schema::create('email_notifications', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('staff_id')->nullable();
            $table->string('subject')->nullable();
        });
    }
}
