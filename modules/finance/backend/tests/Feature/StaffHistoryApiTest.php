<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;
use Modules\Staff\Services\StaffHistoryService;
use Tests\TestCase;

class StaffHistoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->put('user.permissions', [72]);
        $this->createTables();
        $this->seedFixtures();
    }

    public function test_history_includes_former_staff_whose_contract_overlapped_the_period(): void
    {
        $payload = $this->historyPayload([
            'period_from' => '2025-01-01',
            'period_to' => '2025-12-31',
        ]);

        $names = array_column($payload['data'], 'fname');
        $this->assertContains('Carol', $names);
        $this->assertNotContains('Alice', $names);
        $this->assertSame(1, $payload['meta']['total']);
    }

    public function test_history_excludes_contracts_outside_the_period(): void
    {
        $payload = $this->historyPayload([
            'period_from' => '2026-01-01',
            'period_to' => '2026-12-31',
        ]);

        $names = array_column($payload['data'], 'fname');
        $this->assertContains('Alice', $names);
        $this->assertContains('Dan', $names);
        $this->assertNotContains('Carol', $names);
    }

    public function test_history_picks_the_contract_with_the_largest_overlap(): void
    {
        $payload = $this->historyPayload([
            'period_from' => '2026-01-01',
            'period_to' => '2026-12-31',
        ]);

        $dan = collect($payload['data'])->firstWhere('fname', 'Dan');
        $this->assertNotNull($dan);
        $this->assertSame('2026-01-01', $dan['start_date']);
        $this->assertSame('2026-12-31', $dan['end_date']);
        $this->assertSame('Advisor', $dan['job_name']);
    }

    public function test_history_tie_breaks_on_newer_contract_id(): void
    {
        $payload = $this->historyPayload([
            'period_from' => '2024-03-01',
            'period_to' => '2024-03-31',
        ]);

        $eve = collect($payload['data'])->firstWhere('fname', 'Eve');
        $this->assertNotNull($eve);
        $this->assertSame('Later role', $eve['job_name']);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    protected function historyPayload(array $query): array
    {
        $response = app(StaffApiController::class)->history(
            Request::create('/api/v1/staff/history', 'GET', $query),
            app(StaffHistoryService::class),
        );

        $this->assertSame(200, $response->getStatusCode());

        return $response->getData(true);
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
            $table->string('photo')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('initiation_date')->nullable();
            $table->string('work_email')->nullable();
            $table->string('tel_1')->nullable();
            $table->string('tel_2')->nullable();
            $table->string('whatsapp')->nullable();
            $table->integer('nationality_id')->nullable();
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->integer('staff_contract_id')->primary();
            $table->integer('staff_id');
            $table->integer('contract_type_id')->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('duty_station_id')->nullable();
            $table->integer('job_id')->nullable();
            $table->integer('job_acting_id')->nullable();
            $table->integer('funder_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });

        Schema::create('contract_types', function (Blueprint $table): void {
            $table->integer('contract_type_id')->primary();
            $table->string('contract_type');
            $table->string('category');
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->integer('grade_id')->primary();
            $table->string('grade')->nullable();
        });

        Schema::create('nationalities', function (Blueprint $table): void {
            $table->integer('nationality_id')->primary();
            $table->string('nationality')->nullable();
            $table->integer('region_id')->nullable();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('region_name')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->integer('division_id')->primary();
            $table->string('division_name')->nullable();
        });

        Schema::create('duty_stations', function (Blueprint $table): void {
            $table->integer('duty_station_id')->primary();
            $table->string('duty_station_name')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->integer('job_id')->primary();
            $table->string('job_name')->nullable();
        });

        Schema::create('jobs_acting', function (Blueprint $table): void {
            $table->integer('job_acting_id')->primary();
            $table->string('job_acting')->nullable();
        });

        Schema::create('funders', function (Blueprint $table): void {
            $table->integer('funder_id')->primary();
            $table->string('funder')->nullable();
        });

        Schema::create('status', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        \DB::table('contract_types')->insert([
            ['contract_type_id' => 1, 'contract_type' => 'Permanent', 'category' => 'main_staff'],
        ]);
        \DB::table('divisions')->insert([
            ['division_id' => 1, 'division_name' => 'People'],
        ]);
        \DB::table('jobs')->insert([
            ['job_id' => 1, 'job_name' => 'Advisor'],
            ['job_id' => 2, 'job_name' => 'Short role'],
            ['job_id' => 3, 'job_name' => 'Later role'],
        ]);
        \DB::table('status')->insert([
            ['status_id' => 1, 'status' => 'Active'],
            ['status_id' => 4, 'status' => 'Former'],
        ]);

        \DB::table('staff')->insert([
            ['staff_id' => 100, 'fname' => 'Alice', 'lname' => 'Main', 'work_email' => 'alice@example.test'],
            ['staff_id' => 300, 'fname' => 'Carol', 'lname' => 'Former', 'work_email' => 'carol@example.test'],
            ['staff_id' => 400, 'fname' => 'Dan', 'lname' => 'Overlap', 'work_email' => 'dan@example.test'],
            ['staff_id' => 500, 'fname' => 'Eve', 'lname' => 'Tie', 'work_email' => 'eve@example.test'],
        ]);

        \DB::table('staff_contracts')->insert([
            [
                'staff_contract_id' => 1000,
                'staff_id' => 100,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 1,
                'start_date' => '2026-01-01',
                'end_date' => null,
            ],
            [
                'staff_contract_id' => 3000,
                'staff_id' => 300,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 4,
                'start_date' => '2025-01-01',
                'end_date' => '2025-06-30',
            ],
            [
                'staff_contract_id' => 4001,
                'staff_id' => 400,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 2,
                'status_id' => 4,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ],
            [
                'staff_contract_id' => 4002,
                'staff_id' => 400,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 1,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            [
                'staff_contract_id' => 5001,
                'staff_id' => 500,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 4,
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-31',
            ],
            [
                'staff_contract_id' => 5002,
                'staff_id' => 500,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 3,
                'status_id' => 4,
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-31',
            ],
        ]);
    }
}
