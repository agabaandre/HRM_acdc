<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;
use Modules\Staff\Services\StaffContractService;
use Modules\Staff\Services\StaffCreateService;
use Tests\TestCase;

class StaffCreateApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->put('user.permissions', [71]);

        $this->createTables();
        $this->seedFixtures();
    }

    public function test_form_lookups_returns_biodata_and_contract_options_for_manage_staff_users(): void
    {
        $response = app(StaffApiController::class)->formLookups(app(StaffContractService::class));

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Kenya', $payload['data']['nationalities'][0]['nationality']);
        $this->assertSame('Professional', $payload['data']['grades'][0]['grade']);
        $this->assertSame('HQ', $payload['data']['dutyStations'][0]['duty_station_name']);
        $this->assertSame('Unit A', $payload['data']['units'][0]['unit_name']);
        $this->assertContains(50, array_column($payload['data']['supervisors'], 'staff_id'));
    }

    public function test_form_lookups_requires_manage_staff_permission(): void
    {
        session()->put('user.permissions', [72]);

        $response = app(StaffApiController::class)->formLookups(app(StaffContractService::class));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_store_creates_staff_and_first_active_contract(): void
    {
        $response = app(StaffApiController::class)->store(
            Request::create('/api/v1/staff', 'POST', $this->payload(['status_id' => 7])),
            app(StaffCreateService::class)
        );

        $body = $response->getData(true);
        $staffId = (int) $body['data']['staff_id'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertGreaterThan(0, $staffId);
        $this->assertSame('new.staff@example.test', DB::table('staff')->where('staff_id', $staffId)->value('work_email'));
        $this->assertSame('SN-100', DB::table('staff')->where('staff_id', $staffId)->value('SAPNO'));
        $this->assertSame(1, (int) DB::table('staff_contracts')->where('staff_id', $staffId)->value('status_id'));
        $this->assertSame('[2]', DB::table('staff_contracts')->where('staff_id', $staffId)->value('other_associated_divisions'));
        $this->assertSame(1, (int) DB::table('user')->where('auth_staff_id', $staffId)->value('status'));
    }

    public function test_store_requires_manage_staff_permission(): void
    {
        session()->put('user.permissions', [72]);

        $response = app(StaffApiController::class)->store(
            Request::create('/api/v1/staff', 'POST', $this->payload()),
            app(StaffCreateService::class)
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_store_validates_duplicate_email_age_and_contract_dates(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(StaffApiController::class)->store(
                Request::create('/api/v1/staff', 'POST', $this->payload([
                    'work_email' => 'existing@example.test',
                    'date_of_birth' => now()->subYears(17)->toDateString(),
                    'start_date' => '2026-08-15',
                    'end_date' => '2026-08-01',
                ])),
                app(StaffCreateService::class)
            );
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('work_email', $e->errors());
            $this->assertArrayHasKey('date_of_birth', $e->errors());
            $this->assertArrayHasKey('end_date', $e->errors());

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'SAPNO' => 'SN-100',
            'title' => 'Ms',
            'fname' => 'New',
            'lname' => 'Staff',
            'oname' => 'Portal',
            'date_of_birth' => '1990-05-01',
            'gender' => 'Female',
            'nationality_id' => 1,
            'initiation_date' => '2026-08-01',
            'tel_1' => '+251700000001',
            'tel_2' => '+251700000002',
            'whatsapp' => '+251700000003',
            'work_email' => 'new.staff@example.test',
            'private_email' => 'new.staff.private@example.test',
            'physical_location' => 'Addis Ababa',
            'job_id' => 1,
            'job_acting_id' => 1,
            'grade_id' => 'P1',
            'contracting_institution_id' => 1,
            'funder_id' => 1,
            'first_supervisor' => 50,
            'second_supervisor' => 51,
            'contract_type_id' => 1,
            'duty_station_id' => 1,
            'division_id' => 1,
            'unit_id' => 10,
            'other_associated_divisions' => [2],
            'start_date' => '2026-08-01',
            'end_date' => '2027-08-01',
            'comments' => 'Initial contract',
        ], $overrides);
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->increments('staff_id');
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

        Schema::create('nationalities', function (Blueprint $table): void {
            $table->integer('nationality_id')->primary();
            $table->string('nationality')->nullable();
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
            ['status_id' => 6, 'status' => 'Renewed'],
            ['status_id' => 7, 'status' => 'Under Renewal'],
        ]);
        DB::table('nationalities')->insert([
            ['nationality_id' => 1, 'nationality' => 'Kenya'],
        ]);
        DB::table('setting')->insert([
            'default_password' => 'welcome123',
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
                'staff_id' => 99,
                'fname' => 'Existing',
                'lname' => 'Person',
                'work_email' => 'existing@example.test',
            ],
        ]);
    }
}
