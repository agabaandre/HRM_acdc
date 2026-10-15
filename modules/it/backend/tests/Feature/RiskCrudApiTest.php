<?php

namespace Tests\Feature;

use App\Support\RiskPermissions;
use Database\Seeders\RiskLookupSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskCrudApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        $this->createTables();
        $this->seed(RiskLookupSeeder::class);
    }

    public function test_create_risk_writes_audit_and_owners(): void
    {
        $token = 'test-risk-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 62,
            'division_id' => 35,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $themeId = (int) DB::table('rr_enterprise_themes')->value('id');
        $typeId = (int) DB::table('rr_risk_types')->value('id');

        $res = $this->withToken($token)->postJson('/api/v1/risks', [
            'name' => 'API created risk',
            'division_id' => 35,
            'enterprise_theme_id' => $themeId,
            'risk_type_id' => $typeId,
            'inherent_likelihood' => 4,
            'inherent_impact' => 5,
            'owner_staff_ids' => [169, 62],
        ]);

        $res->assertCreated();
        $id = (int) $res->json('data.id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame(20, (int) $res->json('data.inherent_score'));

        $owners = DB::table('rr_risk_owners')->where('risk_id', $id)->pluck('staff_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $this->assertSame([62, 169], $owners);

        $audit = DB::table('rr_audit_logs')->where('entity_type', 'rr_risks')->where('entity_id', $id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('created', $audit->action);
        $this->assertSame(62, (int) $audit->actor_staff_id);
    }

    private function createTables(): void
    {
        foreach ([
            'rr_likelihoods' => function (Blueprint $table) {
                $table->id();
                $table->string('label', 64);
                $table->unsignedTinyInteger('score')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_impacts' => function (Blueprint $table) {
                $table->id();
                $table->string('label', 64);
                $table->unsignedTinyInteger('score')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_risk_types' => function (Blueprint $table) {
                $table->id();
                $table->string('name', 128)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_enterprise_themes' => function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->nullable();
                $table->string('name', 255)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_statuses' => function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_mitigation_effectiveness' => function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->unsignedTinyInteger('likelihood_reduction')->default(0);
                $table->boolean('is_assessed')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_rating_bands' => function (Blueprint $table) {
                $table->id();
                $table->string('rating', 32);
                $table->string('band_key', 32)->nullable();
                $table->unsignedTinyInteger('min_score');
                $table->unsignedTinyInteger('max_score');
                $table->string('fill_color', 7)->nullable();
                $table->string('text_color', 7)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            },
            'rr_rating_key_versions' => function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('version')->unique();
                $table->string('label', 128)->nullable();
                $table->json('bands_json');
                $table->timestamps();
            },
            'rr_settings' => function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            },
            'rr_risks' => function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('import_row_number')->nullable();
                $table->string('source_business_unit', 255)->nullable();
                $table->unsignedInteger('division_id')->nullable();
                $table->unsignedInteger('directorate_id')->nullable();
                $table->string('unmapped_business_unit', 255)->nullable();
                $table->unsignedBigInteger('enterprise_theme_id')->nullable();
                $table->string('name', 512);
                $table->text('consequence')->nullable();
                $table->text('root_causes')->nullable();
                $table->unsignedBigInteger('risk_type_id')->nullable();
                $table->unsignedTinyInteger('inherent_likelihood')->nullable();
                $table->unsignedTinyInteger('inherent_impact')->nullable();
                $table->unsignedTinyInteger('inherent_score')->nullable();
                $table->string('inherent_rating', 32)->nullable();
                $table->string('inherent_rating_key', 32)->nullable();
                $table->text('mitigation')->nullable();
                $table->text('management_response')->nullable();
                $table->string('timeline', 255)->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->text('action_update')->nullable();
                $table->date('date_of_update')->nullable();
                $table->text('oio_verification_notes')->nullable();
                $table->unsignedBigInteger('mitigation_effectiveness_id')->nullable();
                $table->unsignedTinyInteger('residual_likelihood')->nullable();
                $table->unsignedTinyInteger('residual_impact')->nullable();
                $table->unsignedTinyInteger('residual_score')->nullable();
                $table->string('residual_rating', 32)->nullable();
                $table->string('residual_rating_key', 32)->nullable();
                $table->unsignedInteger('rating_key_version')->nullable();
                $table->string('risk_movement', 64)->nullable();
                $table->string('workflow_state', 64)->default('draft');
                $table->boolean('imported')->default(false);
                $table->timestamps();
            },
            'rr_risk_owners' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('risk_id');
                $table->unsignedInteger('staff_id');
                $table->boolean('is_default_hod')->default(false);
                $table->timestamps();
            },
            'rr_audit_logs' => function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('actor_staff_id')->nullable();
                $table->string('action', 64);
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->timestamps();
            },
        ] as $table => $callback) {
            Schema::create($table, $callback);
        }
    }
}
