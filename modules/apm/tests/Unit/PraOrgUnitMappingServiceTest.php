<?php

use App\Models\PraOrgUnitMapping;
use App\Services\Pra\PraClient;
use App\Services\Pra\PraOrgUnitMappingService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('pra_org_unit_mappings');
    Schema::dropIfExists('directorates');
    Schema::dropIfExists('divisions');
    Schema::dropIfExists('system_settings');

    Schema::create('divisions', function (Blueprint $table) {
        $table->id();
        $table->string('division_name')->nullable();
        $table->string('division_short_name')->nullable();
        $table->timestamps();
    });

    Schema::create('directorates', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('pra_org_unit_mappings', function (Blueprint $table) {
        $table->id();
        $table->string('pra_code', 64);
        $table->string('pra_division_id', 64)->nullable();
        $table->string('pra_name')->nullable();
        $table->string('entity_type', 32)->default('division');
        $table->unsignedBigInteger('local_division_id')->nullable();
        $table->unsignedBigInteger('local_directorate_id')->nullable();
        $table->string('match_source', 32)->default('manual');
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamps();
        $table->unique('pra_code');
    });

    Schema::create('system_settings', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->text('value')->nullable();
        $table->string('group')->nullable();
        $table->string('type')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('pra_org_unit_mappings');
    Schema::dropIfExists('directorates');
    Schema::dropIfExists('divisions');
    Schema::dropIfExists('system_settings');
    Mockery::close();
});

function praSettingsMock(array $aliases = ['MIS' => 'DHIS']): PraSettingsService
{
    $settings = Mockery::mock(PraSettingsService::class);
    $settings->shouldReceive('resolved')->andReturn([
        'base_url' => 'https://pra.example.org/api/public/workplan',
        'api_key' => 'test',
        'tiers' => '3,4',
        'fiscal_year' => 2026,
        'division_aliases' => $aliases,
        'timeout' => 60,
    ]);

    return $settings;
}

test('sync auto-maps new units by short name and aliases and stores pra_division_id', function () {
    DB::table('divisions')->insert([
        ['id' => 1, 'division_name' => 'Surveillance', 'division_short_name' => 'SDI', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'division_name' => 'Digital Health', 'division_short_name' => 'DHIS', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $client = Mockery::mock(PraClient::class);
    $client->shouldReceive('fetchWorkplan')->once()->andReturn([
        'success' => true,
        'meta' => [],
        'data' => [
            ['id' => 1, 'division' => ['id' => 101, 'code' => 'SDI', 'name' => 'Surveillance & Disease Intelligence']],
            ['id' => 2, 'division' => ['code' => 'MIS', 'name' => 'Division of MIS']],
            ['id' => 3, 'division' => ['code' => 'ADMIN', 'name' => 'Directorate of Administration']],
        ],
    ]);

    $service = new PraOrgUnitMappingService($client, praSettingsMock());
    $preview = $service->syncFromPra(2026);

    expect($preview['summary']['total'])->toBe(3)
        ->and($preview['summary']['matched'])->toBe(2)
        ->and($preview['summary']['added'])->toBe(3)
        ->and($preview['summary']['preserved'])->toBe(0);

    $byCode = collect($preview['units'])->keyBy('pra_code');
    expect($byCode['SDI']['pra_division_id'])->toBe('101')
        ->and($byCode['MIS']['pra_division_id'])->toBe('MIS')
        ->and($byCode['MIS']['match_source'])->toBe('alias')
        ->and(PraOrgUnitMapping::query()->count())->toBe(3);
});

test('resync preserves manual and auto matches and only adds new codes', function () {
    DB::table('divisions')->insert([
        ['id' => 1, 'division_name' => 'Surveillance', 'division_short_name' => 'SDI', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 10, 'division_name' => 'Custom mapped', 'division_short_name' => 'XYZ', 'created_at' => now(), 'updated_at' => now()],
    ]);

    PraOrgUnitMapping::query()->create([
        'pra_code' => 'SDI',
        'pra_division_id' => 'SDI',
        'pra_name' => 'Old SDI name',
        'entity_type' => 'division',
        'local_division_id' => 1,
        'match_source' => 'auto',
    ]);
    PraOrgUnitMapping::query()->create([
        'pra_code' => 'NEWISH',
        'pra_division_id' => 'NEWISH',
        'pra_name' => 'Manually mapped',
        'entity_type' => 'division',
        'local_division_id' => 10,
        'match_source' => 'manual',
    ]);

    $client = Mockery::mock(PraClient::class);
    $client->shouldReceive('fetchWorkplan')->once()->andReturn([
        'success' => true,
        'meta' => [],
        'data' => [
            ['id' => 1, 'division' => ['code' => 'SDI', 'name' => 'Surveillance UPDATED']],
            ['id' => 2, 'division' => ['code' => 'NEWISH', 'name' => 'Still manual']],
            ['id' => 3, 'division' => ['code' => 'BRANDNEW', 'name' => 'Brand new unmatched']],
        ],
    ]);

    $service = new PraOrgUnitMappingService($client, praSettingsMock([]));
    $result = $service->syncFromPra(2026);

    expect($result['summary']['added'])->toBe(1)
        ->and($result['summary']['preserved'])->toBe(2)
        ->and($result['summary']['unmatched'])->toBe(1);

    $sdi = PraOrgUnitMapping::query()->where('pra_code', 'SDI')->first();
    expect($sdi->local_division_id)->toBe(1)
        ->and($sdi->match_source)->toBe('auto')
        ->and($sdi->pra_name)->toBe('Surveillance UPDATED');

    $manual = PraOrgUnitMapping::query()->where('pra_code', 'NEWISH')->first();
    expect($manual->local_division_id)->toBe(10)
        ->and($manual->match_source)->toBe('manual');

    $brand = PraOrgUnitMapping::query()->where('pra_code', 'BRANDNEW')->first();
    expect($brand)->not->toBeNull()
        ->and($brand->local_division_id)->toBeNull()
        ->and($brand->pra_division_id)->toBe('BRANDNEW')
        ->and($service->resolveDivisionId('NEWISH'))->toBe(10);
});

test('save mappings persists manual match with pra_division_id', function () {
    DB::table('divisions')->insert([
        'id' => 10,
        'division_name' => 'Planning',
        'division_short_name' => 'PRA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $client = Mockery::mock(PraClient::class);
    $service = new PraOrgUnitMappingService($client, praSettingsMock([]));
    $count = $service->saveMappings([
        [
            'pra_code' => 'XYZ',
            'pra_division_id' => '42',
            'pra_name' => 'Unknown Unit',
            'entity_type' => 'division',
            'local_division_id' => 10,
            'match_source' => 'manual',
            'user_set' => true,
        ],
    ]);

    expect($count)->toBe(1);
    $row = PraOrgUnitMapping::query()->where('pra_code', 'XYZ')->first();
    expect($row->pra_division_id)->toBe('42')
        ->and($service->resolveDivisionId('42'))->toBe(10)
        ->and($service->resolveDivisionId('XYZ'))->toBe(10);
});
