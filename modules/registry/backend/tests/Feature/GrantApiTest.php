<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrantApiTest extends TestCase
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
        Schema::create('fin_grants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_name')->nullable();
            $table->unsignedBigInteger('funder_id')->nullable();
            $table->string('funder_name')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_budget', 18, 2)->nullable();
            $table->decimal('direct_cost_usd', 18, 2)->nullable();
            $table->decimal('indirect_cost', 18, 2)->nullable();
            $table->decimal('indirect_pct', 8, 4)->nullable();
            $table->string('budget_code', 64)->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        config(['database.connections.apm' => config('database.connections.sqlite')]);
        DB::purge('apm');
        Schema::connection('apm')->create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        DB::connection('apm')->table('funders')->insert(['name' => 'CEPI', 'is_active' => 1]);
    }

    public function test_crud_grants_and_search(): void
    {
        $token = 'grants-manage';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MANAGE_FUNDING_PORTFOLIO],
            'api_token' => $token,
        ], 3600);

        $create = $this->withToken($token)->postJson('/api/v1/grants', [
            'name' => 'CEPI – Africa CDC Funding Agreement',
            'short_name' => 'CEPI FA',
            'funder_id' => 1,
            'total_budget' => 7200000,
            'is_active' => true,
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.grant.funder_name', 'CEPI');
        $id = (int) $create->json('data.grant.id');

        $list = $this->withToken($token)->getJson('/api/v1/grants?q=CEPI');
        $list->assertOk();
        $this->assertCount(1, $list->json('data.grants'));

        $update = $this->withToken($token)->putJson('/api/v1/grants/'.$id, [
            'name' => 'CEPI – Africa CDC Funding Agreement',
            'short_name' => 'CEPI updated',
            'funder_id' => 1,
            'total_budget' => 8000000,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.grant.short_name', 'CEPI updated');

        $viewer = 'grants-view';
        Cache::put('risk_api_token:'.$viewer, [
            'staff_id' => 2,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MODULE],
            'api_token' => $viewer,
        ], 3600);
        $denied = $this->withToken($viewer)->postJson('/api/v1/grants', ['name' => 'Nope']);
        $denied->assertStatus(403);

        $delete = $this->withToken($token)->deleteJson('/api/v1/grants/'.$id);
        $delete->assertOk();
        $this->assertSame(0, DB::table('fin_grants')->count());
    }
}
