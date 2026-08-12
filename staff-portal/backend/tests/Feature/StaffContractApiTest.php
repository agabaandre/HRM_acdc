<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;
use Modules\Staff\Services\StaffContractService;
use Tests\TestCase;

class StaffContractApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        session()->put('user.permissions', [71]);

        $this->createTables();
        $this->seedFixtures();
    }

    public function test_store_contract_creates_a_new_contract_and_demotes_the_previous_current_contract(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 1,
            'start_date' => '2026-01-01',
            'end_date' => now()->addYear()->toDateString(),
            'comments' => 'Previous active contract',
        ]);

        $response = app(StaffApiController::class)->storeContract(
            100,
            Request::create('/api/v1/staff/100/contracts', 'POST', $this->payload([
                'status_id' => 1,
                'start_date' => '2026-08-01',
                'end_date' => now()->addYears(2)->toDateString(),
                'comments' => 'Renewed contract',
            ])),
            app(StaffContractService::class)
        );

        $payload = $response->getData(true);
        $newContractId = (int) $payload['data']['contract_id'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Contract created successfully.', $payload['message']);
        $this->assertGreaterThan(0, $newContractId);
        $this->assertSame(6, (int) DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('status_id'));
        $this->assertSame(1, (int) DB::table('staff_contracts')->where('staff_contract_id', $newContractId)->value('status_id'));
        $this->assertSame('Renewed contract', DB::table('staff_contracts')->where('staff_contract_id', $newContractId)->value('comments'));
    }

    public function test_update_contract_updates_an_existing_contract(): void
    {
        $this->insertContract([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'status_id' => 7,
            'start_date' => '2026-01-01',
            'end_date' => now()->addYear()->toDateString(),
            'comments' => 'Before update',
        ]);

        $response = app(StaffApiController::class)->updateContract(
            100,
            1000,
            Request::create('/api/v1/staff/100/contracts/1000', 'PUT', $this->payload([
                'status_id' => 4,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-31',
                'comments' => 'Separated by API update',
                'other_associated_divisions' => [2],
            ])),
            app(StaffContractService::class)
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Contract updated successfully.', $payload['message']);
        $this->assertSame(1000, (int) $payload['data']['contract_id']);
        $this->assertSame(4, (int) DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('status_id'));
        $this->assertSame('2026-02-01', DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('start_date'));
        $this->assertSame('[2]', DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('other_associated_divisions'));
        $this->assertSame('Separated by API update', DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('comments'));
    }

    public function test_update_contract_returns_422_json_when_another_current_contract_exists(): void
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

        $response = app(StaffApiController::class)->updateContract(
            100,
            1000,
            Request::create('/api/v1/staff/100/contracts/1000', 'PUT', $this->payload([
                'status_id' => 1,
                'start_date' => '2025-01-01',
                'end_date' => now()->addYear()->toDateString(),
            ])),
            app(StaffContractService::class)
        );

        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('The given data was invalid.', $payload['message']);
        $this->assertSame(
            ['A current contract already exists for this staff member.'],
            $payload['errors']['status_id'] ?? null
        );
        $this->assertSame(6, (int) DB::table('staff_contracts')->where('staff_contract_id', 1000)->value('status_id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'job_id' => 1,
            'job_acting_id' => '',
            'grade_id' => 'P1',
            'contracting_institution_id' => 1,
            'funder_id' => 1,
            'first_supervisor' => 50,
            'second_supervisor' => 51,
            'contract_type_id' => 1,
            'duty_station_id' => 1,
            'division_id' => 1,
            'unit_id' => 10,
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
        DB::table('staff_contracts')->insert(array_merge([
            'job_id' => 1,
            'job_acting_id' => null,
            'grade_id' => 'P1',
            'contracting_institution_id' => 1,
            'funder_id' => 1,
            'first_supervisor' => 50,
            'second_supervisor' => 51,
            'contract_type_id' => 1,
            'duty_station_id' => 1,
            'division_id' => 1,
            'unit_id' => 10,
            'other_associated_divisions' => null,
            'comments' => '',
            'file_name' => null,
        ], $attributes));
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('SAPNO')->nullable();
            $table->string('title')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('oname')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->integer('nationality_id')->nullable();
            $table->date('initiation_date')->nullable();
            $table->string('tel_1')->nullable();
            $table->string('tel_2')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('work_email')->nullable();
            $table->string('private_email')->nullable();
            $table->text('physical_location')->nullable();
            $table->integer('flag')->default(0);
            $table->integer('email_disabled_by')->default(0);
            $table->integer('email_status')->default(0);
            $table->timestamp('email_disabled_at')->nullable();
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
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

        Schema::create('jobs', function (Blueprint $table): void {
            $table->integer('job_id')->primary();
            $table->string('job_name')->nullable();
        });

        Schema::create('jobs_acting', function (Blueprint $table): void {
            $table->integer('job_acting_id')->primary();
            $table->string('job_acting')->nullable();
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->string('grade_id')->primary();
            $table->string('grade')->nullable();
        });

        Schema::create('contracting_institutions', function (Blueprint $table): void {
            $table->integer('contracting_institution_id')->primary();
            $table->string('contracting_institution')->nullable();
        });

        Schema::create('funders', function (Blueprint $table): void {
            $table->integer('funder_id')->primary();
            $table->string('funder')->nullable();
        });

        Schema::create('contract_types', function (Blueprint $table): void {
            $table->integer('contract_type_id')->primary();
            $table->string('contract_type')->nullable();
            $table->string('category')->nullable();
        });

        Schema::create('duty_stations', function (Blueprint $table): void {
            $table->integer('duty_station_id')->primary();
            $table->string('duty_station_name')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->integer('division_id')->primary();
            $table->string('division_name')->nullable();
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->integer('unit_id')->primary();
            $table->integer('division_id')->nullable();
            $table->string('unit_name')->nullable();
        });

        Schema::create('status', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status')->nullable();
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->integer('auth_staff_id')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(0);
        });

        Schema::create('setting', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('default_password')->nullable();
        });

        Schema::create('email_notifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id')->nullable();
            $table->string('subject')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('jobs')->insert([
            ['job_id' => 1, 'job_name' => 'Advisor'],
        ]);
        DB::table('jobs_acting')->insert([
            ['job_acting_id' => 1, 'job_acting' => 'Acting Advisor'],
        ]);
        DB::table('grades')->insert([
            ['grade_id' => 'P1', 'grade' => 'Professional'],
        ]);
        DB::table('contracting_institutions')->insert([
            ['contracting_institution_id' => 1, 'contracting_institution' => 'Africa CDC'],
        ]);
        DB::table('funders')->insert([
            ['funder_id' => 1, 'funder' => 'AU'],
        ]);
        DB::table('contract_types')->insert([
            ['contract_type_id' => 1, 'contract_type' => 'Permanent', 'category' => 'main_staff'],
        ]);
        DB::table('duty_stations')->insert([
            ['duty_station_id' => 1, 'duty_station_name' => 'HQ'],
        ]);
        DB::table('divisions')->insert([
            ['division_id' => 1, 'division_name' => 'People'],
            ['division_id' => 2, 'division_name' => 'Operations'],
        ]);
        DB::table('units')->insert([
            ['unit_id' => 10, 'division_id' => 1, 'unit_name' => 'Unit A'],
            ['unit_id' => 20, 'division_id' => 2, 'unit_name' => 'Unit B'],
        ]);
        DB::table('status')->insert([
            ['status_id' => 1, 'status' => 'Active'],
            ['status_id' => 2, 'status' => 'Due'],
            ['status_id' => 3, 'status' => 'Expired'],
            ['status_id' => 4, 'status' => 'Separated'],
            ['status_id' => 5, 'status' => 'Ended'],
            ['status_id' => 6, 'status' => 'Renewed'],
            ['status_id' => 7, 'status' => 'Under Renewal'],
        ]);

        DB::table('staff')->insert([
            [
                'staff_id' => 50,
                'fname' => 'Alice',
                'lname' => 'Supervisor',
                'work_email' => 'alice.supervisor@example.test',
            ],
            [
                'staff_id' => 51,
                'fname' => 'Bob',
                'lname' => 'Supervisor',
                'work_email' => 'bob.supervisor@example.test',
            ],
            [
                'staff_id' => 100,
                'fname' => 'Current',
                'lname' => 'Staff',
                'work_email' => 'current.staff@example.test',
            ],
        ]);
    }
}
