<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeographyApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'logging.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('region_name', 100);
        });
        Schema::create('nationalities', function (Blueprint $table) {
            $table->increments('nationality_id');
            $table->string('nationality', 50);
            $table->string('nationality_name', 50)->nullable();
            $table->string('continent', 50);
            $table->unsignedInteger('region_id')->default(0);
            $table->string('iso2', 2)->nullable();
        });
        Schema::create('duty_stations', function (Blueprint $table) {
            $table->increments('duty_station_id');
            $table->string('duty_station_name', 100);
            $table->string('country', 50)->default('');
            $table->string('type', 50)->default('');
            $table->string('city', 100)->default('');
            $table->string('country_iso2', 8)->nullable();
        });

        DB::table('regions')->insert([
            ['id' => 2, 'region_name' => 'AU_Eastern'],
            ['id' => 4, 'region_name' => 'AU_Southern'],
        ]);
        DB::table('nationalities')->insert([
            'nationality' => 'Ethiopia',
            'nationality_name' => 'Ethiopia',
            'continent' => 'AFRICA',
            'region_id' => 2,
            'iso2' => 'ET',
        ]);
        DB::table('duty_stations')->insert([
            'duty_station_name' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'type' => '',
            'city' => 'Addis Ababa',
        ]);
    }

    public function test_show_lists_and_save_aliases(): void
    {
        $token = 'geo-manage';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MANAGE_FUNDING_PORTFOLIO],
            'api_token' => $token,
        ], 3600);

        $show = $this->withToken($token)->getJson('/api/v1/settings/geography');
        $show->assertOk();
        $show->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(2, count($show->json('data.regions')));
        $this->assertCount(1, $show->json('data.countries'));
        $this->assertCount(1, $show->json('data.locations'));
        $this->assertNotEmpty($show->json('data.region_aliases'));

        $save = $this->withToken($token)->putJson('/api/v1/settings/geography/aliases', [
            'region_aliases' => [
                ['label' => 'East Africa', 'region_id' => 2, 'match_source' => 'manual'],
                ['label' => 'Southern Africa', 'region_id' => 4, 'match_source' => 'manual'],
            ],
        ]);
        $save->assertOk();
        $this->assertSame(2, $save->json('data.region_aliases.0.region_id'));
    }

    public function test_crud_region_country_location(): void
    {
        $token = 'geo-crud';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MANAGE_FUNDING_PORTFOLIO],
            'api_token' => $token,
        ], 3600);

        $region = $this->withToken($token)->postJson('/api/v1/settings/geography/regions', [
            'name' => 'AU_Western',
        ]);
        $region->assertCreated();
        $regionId = (int) $region->json('data.id');

        $country = $this->withToken($token)->postJson('/api/v1/settings/geography/countries', [
            'name' => 'Nigeria',
            'region_id' => $regionId,
            'iso2' => 'NG',
        ]);
        $country->assertCreated();

        $location = $this->withToken($token)->postJson('/api/v1/settings/geography/locations', [
            'name' => 'Abuja',
            'country' => 'Nigeria',
            'city' => 'Abuja',
        ]);
        $location->assertCreated();
    }
}
