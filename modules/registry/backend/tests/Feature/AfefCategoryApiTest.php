<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AfefCategoryApiTest extends TestCase
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
        Schema::create('fin_portfolio_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 64);
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data');
            $table->timestamps();
        });
    }

    public function test_defaults_and_save_categories(): void
    {
        $token = 'afef-cat';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MANAGE_FUNDING_PORTFOLIO],
            'api_token' => $token,
        ], 3600);

        $show = $this->withToken($token)->getJson('/api/v1/settings/afef-categories');
        $show->assertOk();
        $this->assertGreaterThanOrEqual(5, count($show->json('data.categories')));
        $this->assertTrue($show->json('data.can_edit'));

        $save = $this->withToken($token)->putJson('/api/v1/settings/afef-categories', [
            'categories' => [
                ['key' => 'opening', 'label' => 'Opening', 'sort_order' => 10, 'is_active' => true],
                ['key' => 'income', 'label' => 'Income', 'sort_order' => 20, 'is_active' => true],
                ['key' => 'custom', 'label' => 'Custom line', 'sort_order' => 30, 'is_active' => false],
            ],
        ]);
        $save->assertOk();
        $this->assertCount(3, $save->json('data.categories'));
        $this->assertFalse($save->json('data.categories.2.is_active'));

        $viewer = 'afef-viewer';
        Cache::put('risk_api_token:'.$viewer, [
            'staff_id' => 2,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MODULE],
            'api_token' => $viewer,
        ], 3600);
        $denied = $this->withToken($viewer)->putJson('/api/v1/settings/afef-categories', [
            'categories' => [['key' => 'x', 'label' => 'X']],
        ]);
        $denied->assertStatus(403);

        $section = $this->withToken($token)->getJson('/api/v1/portfolio/sections/afef');
        $section->assertOk();
        $this->assertCount(2, $section->json('data.afef_categories'));
        $this->assertSame('afef_category', $section->json('data.schema.0.type'));
    }
}
