<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Services\CsvExportService;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;
use Modules\Staff\Services\StaffDirectoryService;
use Tests\TestCase;

class StaffDirectoryCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->put('user.permissions', [72]);

        $this->createDirectoryTables();
        $this->seedDirectoryFixtures();
    }

    public function test_directory_defaults_to_main_staff_and_includes_photo_fields(): void
    {
        $response = app(StaffApiController::class)->index(
            Request::create('/api/v1/staff', 'GET'),
            app(StaffDirectoryService::class)
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Alice', $payload['data'][0]['fname']);
        $this->assertSame('alice.jpg', $payload['data'][0]['photo']);
        $this->assertSame('Permanent', $payload['data'][0]['contract_type']);
        $this->assertSame('main_staff', $payload['data'][0]['category']);
    }

    public function test_export_csv_uses_category_filter(): void
    {
        $response = app(StaffApiController::class)->exportCsv(
            Request::create('/api/v1/staff/export.csv', 'GET', ['category' => 'other_staff']),
            app(StaffDirectoryService::class),
            app(CsvExportService::class)
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Bob', $csv);
        $this->assertStringNotContainsString('Alice', $csv);
    }

    public function test_export_csv_uses_contract_status_label(): void
    {
        $response = app(StaffApiController::class)->exportCsv(
            Request::create('/api/v1/staff/export.csv', 'GET'),
            app(StaffDirectoryService::class),
            app(CsvExportService::class)
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertGreaterThan(1, count($lines));

        $header = str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"));
        $statusIndex = array_search('Status', $header, true);
        $this->assertNotFalse($statusIndex);
        $categoryIndex = array_search('Category', $header, true);
        $this->assertNotFalse($categoryIndex);

        $alice = null;
        foreach (array_slice($lines, 1) as $line) {
            $cols = str_getcsv($line);
            if (($cols[1] ?? '') === 'Alice') {
                $alice = $cols;
                break;
            }
        }

        $this->assertNotNull($alice);
        $this->assertSame('Active', $alice[$statusIndex]);
        $this->assertNotSame('1', $alice[$statusIndex]);
        $this->assertSame('Advisor', $alice[array_search('Job', $header, true)]);
        $this->assertSame('People', $alice[array_search('Division', $header, true)]);
        $this->assertSame('Main staff', $alice[$categoryIndex]);
    }

    public function test_export_csv_is_not_clamped_to_list_page_size(): void
    {
        $this->seedExtraMainStaff(101);

        $list = app(StaffApiController::class)->index(
            Request::create('/api/v1/staff', 'GET', ['per_page' => 2000]),
            app(StaffDirectoryService::class)
        )->getData(true);

        $this->assertSame(102, $list['meta']['total']);
        $this->assertSame(100, $list['meta']['per_page']);
        $this->assertCount(100, $list['data']);

        $response = app(StaffApiController::class)->exportCsv(
            Request::create('/api/v1/staff/export.csv', 'GET'),
            app(StaffDirectoryService::class),
            app(CsvExportService::class)
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $dataRows = array_values(array_filter(explode("\n", trim($csv))));
        array_shift($dataRows);

        $this->assertCount(102, $dataRows);
    }

    protected function createDirectoryTables(): void
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

    protected function seedDirectoryFixtures(): void
    {
        \DB::table('contract_types')->insert([
            ['contract_type_id' => 1, 'contract_type' => 'Permanent', 'category' => 'main_staff'],
            ['contract_type_id' => 2, 'contract_type' => 'Consultant', 'category' => 'other_staff'],
        ]);

        \DB::table('divisions')->insert([
            ['division_id' => 1, 'division_name' => 'People'],
        ]);

        \DB::table('jobs')->insert([
            ['job_id' => 1, 'job_name' => 'Advisor'],
        ]);

        \DB::table('status')->insert([
            ['status_id' => 1, 'status' => 'Active'],
        ]);

        \DB::table('staff')->insert([
            [
                'staff_id' => 100,
                'fname' => 'Alice',
                'lname' => 'Main',
                'photo' => 'alice.jpg',
                'work_email' => 'alice@example.test',
            ],
            [
                'staff_id' => 200,
                'fname' => 'Bob',
                'lname' => 'Other',
                'photo' => 'bob.jpg',
                'work_email' => 'bob@example.test',
            ],
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
            ],
            [
                'staff_contract_id' => 2000,
                'staff_id' => 200,
                'contract_type_id' => 2,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 1,
                'start_date' => '2026-01-01',
            ],
        ]);
    }

    protected function seedExtraMainStaff(int $count): void
    {
        $staff = [];
        $contracts = [];

        for ($i = 1; $i <= $count; $i++) {
            $staffId = 1000 + $i;
            $staff[] = [
                'staff_id' => $staffId,
                'fname' => 'Extra'.$i,
                'lname' => 'Staff',
                'photo' => 'extra'.$i.'.jpg',
                'work_email' => 'extra'.$i.'@example.test',
            ];
            $contracts[] = [
                'staff_contract_id' => 10000 + $i,
                'staff_id' => $staffId,
                'contract_type_id' => 1,
                'division_id' => 1,
                'job_id' => 1,
                'status_id' => 1,
                'start_date' => '2026-01-01',
            ];
        }

        \DB::table('staff')->insert($staff);
        \DB::table('staff_contracts')->insert($contracts);
    }
}
