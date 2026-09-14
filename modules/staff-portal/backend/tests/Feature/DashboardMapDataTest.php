<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Dashboard\Services\DashboardService;
use Tests\TestCase;

class DashboardMapDataTest extends TestCase
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

    public function test_empty_contracts_include_map_keys(): void
    {
        DB::table('staff_contracts')->delete();

        $payload = app(DashboardService::class)->getDashboardData();

        $this->assertSame([], $payload['staff_by_duty_station_map']['points']);
        $this->assertSame(0, $payload['staff_by_duty_station_map']['unmapped']);
        $this->assertSame(0, $payload['staff_by_duty_station_map']['outside_africa']);
        $this->assertSame([], $payload['staff_by_nationality_map']['points']);
        $this->assertSame(0, $payload['staff_by_nationality_map']['unmapped']);
        $this->assertSame(0, $payload['staff_by_nationality_map']['outside_africa']);
    }

    public function test_aggregates_staff_by_duty_station_country_and_nationality(): void
    {
        $payload = app(DashboardService::class)->getDashboardData();

        $stationByIso = $this->pointsByIso2($payload['staff_by_duty_station_map']['points']);
        $this->assertSame(3, $stationByIso['ET']['value']);
        $this->assertSame('Ethiopia', $stationByIso['ET']['name']);
        $this->assertSame('ETH', $stationByIso['ET']['iso-a3']);
        $this->assertSame('et', $stationByIso['ET']['code']);
        $this->assertSame(
            [
                ['name' => 'HQ', 'city' => 'Addis Ababa', 'count' => 2],
                ['name' => 'RCC East', 'city' => 'Nairobi', 'count' => 1],
            ],
            $stationByIso['ET']['stations'],
        );
        $this->assertSame(1, $stationByIso['NG']['value']);
        $this->assertSame(0, $payload['staff_by_duty_station_map']['unmapped']);
        $this->assertSame(1, $payload['staff_by_duty_station_map']['outside_africa']);
        $this->assertFalse($stationByIso['CH']['on_map']);

        $nationalityByIso = $this->pointsByIso2($payload['staff_by_nationality_map']['points']);
        $this->assertSame(2, $nationalityByIso['ET']['value']);
        $this->assertSame(1, $nationalityByIso['KE']['value']);
        $this->assertSame(1, $nationalityByIso['NG']['value']);
        $this->assertSame(1, $nationalityByIso['US']['value']);
        $this->assertTrue($nationalityByIso['ET']['on_map']);
        $this->assertFalse($nationalityByIso['US']['on_map']);
        $this->assertSame(0, $payload['staff_by_nationality_map']['unmapped']);
        $this->assertSame(1, $payload['staff_by_nationality_map']['outside_africa']);
    }

    public function test_resolves_station_country_name_when_iso2_is_blank(): void
    {
        DB::table('duty_stations')->where('duty_station_id', 10)->update([
            'country_iso2' => null,
            'country' => 'Ethiopia',
        ]);

        $payload = app(DashboardService::class)->getDashboardData();
        $stationByIso = $this->pointsByIso2($payload['staff_by_duty_station_map']['points']);

        $this->assertSame(3, $stationByIso['ET']['value']);
    }

    public function test_unmapped_station_and_inactive_contracts_are_excluded_from_country_totals(): void
    {
        DB::table('duty_stations')->insert([
            'duty_station_id' => 40,
            'duty_station_name' => 'Unknown post',
            'country' => 'Narnia',
            'country_iso2' => null,
            'city' => null,
        ]);
        DB::table('staff')->insert([
            ['staff_id' => 10, 'fname' => 'Lost', 'lname' => 'Post', 'gender' => 'Male', 'nationality_id' => null],
            ['staff_id' => 11, 'fname' => 'Old', 'lname' => 'Contract', 'gender' => 'Female', 'nationality_id' => 1],
        ]);
        DB::table('staff_contracts')->insert([
            [
                'staff_contract_id' => 10,
                'staff_id' => 10,
                'duty_station_id' => 40,
                'division_id' => 1,
                'funder_id' => 1,
                'contract_type_id' => 1,
                'job_id' => 1,
                'status_id' => 1,
            ],
            [
                'staff_contract_id' => 11,
                'staff_id' => 11,
                'duty_station_id' => 20,
                'division_id' => 1,
                'funder_id' => 1,
                'contract_type_id' => 1,
                'job_id' => 1,
                'status_id' => 3,
            ],
        ]);

        $payload = app(DashboardService::class)->getDashboardData();
        $stationByIso = $this->pointsByIso2($payload['staff_by_duty_station_map']['points']);

        $this->assertSame(1, $payload['staff_by_duty_station_map']['unmapped']);
        $this->assertSame(1, $stationByIso['NG']['value']);
        $this->assertSame(1, $payload['staff_by_nationality_map']['unmapped']);
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array<string, array<string, mixed>>
     */
    protected function pointsByIso2(array $points): array
    {
        $out = [];
        foreach ($points as $point) {
            $out[(string) $point['iso2']] = $point;
        }

        return $out;
    }

    protected function createTables(): void
    {
        Schema::create('nationalities', function (Blueprint $table): void {
            $table->integer('nationality_id')->primary();
            $table->string('nationality')->nullable();
            $table->string('nationality_name')->nullable();
            $table->string('iso2', 2)->nullable();
            $table->string('iso3', 3)->nullable();
        });
        Schema::create('duty_stations', function (Blueprint $table): void {
            $table->integer('duty_station_id')->primary();
            $table->string('duty_station_name')->nullable();
            $table->string('country')->nullable();
            $table->string('country_iso2', 2)->nullable();
            $table->string('city')->nullable();
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
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('gender')->nullable();
            $table->integer('nationality_id')->nullable();
        });
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->integer('staff_contract_id')->primary();
            $table->integer('staff_id');
            $table->integer('duty_station_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('funder_id')->nullable();
            $table->integer('contract_type_id')->nullable();
            $table->integer('job_id')->nullable();
            $table->integer('status_id')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('nationalities')->insert([
            ['nationality_id' => 1, 'nationality' => 'Kenyan', 'nationality_name' => 'Kenya', 'iso2' => 'KE', 'iso3' => 'KEN'],
            ['nationality_id' => 2, 'nationality' => 'Nigerian', 'nationality_name' => 'Nigeria', 'iso2' => 'NG', 'iso3' => 'NGA'],
            ['nationality_id' => 3, 'nationality' => 'Ethiopian', 'nationality_name' => 'Ethiopia', 'iso2' => 'ET', 'iso3' => 'ETH'],
            ['nationality_id' => 4, 'nationality' => 'American', 'nationality_name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA'],
        ]);
        DB::table('duty_stations')->insert([
            ['duty_station_id' => 10, 'duty_station_name' => 'HQ', 'country' => 'Ethiopia', 'country_iso2' => 'ET', 'city' => 'Addis Ababa'],
            ['duty_station_id' => 20, 'duty_station_name' => 'Abuja', 'country' => 'Nigeria', 'country_iso2' => 'NG', 'city' => 'Abuja'],
            ['duty_station_id' => 30, 'duty_station_name' => 'RCC East', 'country' => 'Ethiopia', 'country_iso2' => 'ET', 'city' => 'Nairobi'],
            ['duty_station_id' => 50, 'duty_station_name' => 'Geneva', 'country' => 'Switzerland', 'country_iso2' => 'CH', 'city' => 'Geneva'],
        ]);
        DB::table('divisions')->insert(['division_id' => 1, 'division_name' => 'OSD']);
        DB::table('funders')->insert(['funder_id' => 1, 'funder' => 'Core']);
        DB::table('contract_types')->insert(['contract_type_id' => 1, 'contract_type' => 'Fixed']);
        DB::table('staff')->insert([
            ['staff_id' => 1, 'fname' => 'Amina', 'lname' => 'HQ', 'gender' => 'Female', 'nationality_id' => 3],
            ['staff_id' => 2, 'fname' => 'Bongani', 'lname' => 'HQ', 'gender' => 'Male', 'nationality_id' => 3],
            ['staff_id' => 3, 'fname' => 'Chidi', 'lname' => 'Abuja', 'gender' => 'Male', 'nationality_id' => 2],
            ['staff_id' => 4, 'fname' => 'Wanjiku', 'lname' => 'East', 'gender' => 'Female', 'nationality_id' => 1],
            ['staff_id' => 5, 'fname' => 'Alex', 'lname' => 'Geneva', 'gender' => 'Male', 'nationality_id' => 4],
        ]);
        DB::table('staff_contracts')->insert([
            $this->contract(1, 1, 10),
            $this->contract(2, 2, 10),
            $this->contract(3, 3, 20),
            $this->contract(4, 4, 30),
            $this->contract(5, 5, 50),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function contract(int $id, int $staffId, int $stationId, int $statusId = 1): array
    {
        return [
            'staff_contract_id' => $id,
            'staff_id' => $staffId,
            'duty_station_id' => $stationId,
            'division_id' => 1,
            'funder_id' => 1,
            'contract_type_id' => 1,
            'job_id' => 1,
            'status_id' => $statusId,
        ];
    }
}
