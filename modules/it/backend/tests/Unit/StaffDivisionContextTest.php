<?php

namespace Tests\Unit;

use App\Support\StaffDivisionContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffDivisionContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('staff_contracts');
        Schema::dropIfExists('divisions');

        Schema::create('divisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('division_id')->primary();
            $table->string('division_name')->nullable();
            $table->string('division_short_name')->nullable();
            $table->unsignedBigInteger('division_head')->nullable();
            $table->unsignedBigInteger('focal_person')->nullable();
            $table->unsignedBigInteger('risk_focal_person')->nullable();
            $table->unsignedBigInteger('admin_assistant')->nullable();
            $table->unsignedBigInteger('finance_officer')->nullable();
            $table->unsignedBigInteger('directorate_id')->nullable();
            $table->unsignedBigInteger('head_oic_id')->nullable();
            $table->date('head_oic_start_date')->nullable();
            $table->date('head_oic_end_date')->nullable();
            $table->unsignedBigInteger('director_id')->nullable();
            $table->unsignedBigInteger('director_oic_id')->nullable();
            $table->date('director_oic_start_date')->nullable();
            $table->date('director_oic_end_date')->nullable();
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->text('other_associated_divisions')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('staff_contracts');
        Schema::dropIfExists('divisions');
        parent::tearDown();
    }

    private function seedDivision(int $id, string $name, array $attrs = []): void
    {
        DB::table('divisions')->insert(array_merge([
            'division_id' => $id,
            'division_name' => $name,
            'division_short_name' => substr($name, 0, 8),
        ], $attrs));
    }

    private function seedContract(int $staffId, int $primary, array $associated = []): void
    {
        DB::table('staff_contracts')->insert([
            'staff_id' => $staffId,
            'division_id' => $primary,
            'status_id' => 1,
            'other_associated_divisions' => json_encode(array_values($associated)),
        ]);
    }

    public function test_switchable_includes_primary_and_associated_but_not_finance_officer_only(): void
    {
        $this->seedDivision(910010, 'Primary Div');
        $this->seedDivision(910020, 'Associated Div');
        $this->seedDivision(910030, 'Finance Only', ['finance_officer' => 910501]);
        $this->seedContract(910501, 910010, [910020]);

        $ids = StaffDivisionContext::switchableIds(910501);

        $this->assertContains(910010, $ids);
        $this->assertContains(910020, $ids);
        $this->assertNotContains(910030, $ids);
    }

    public function test_switchable_includes_focal_and_risk_focal_and_director(): void
    {
        $this->seedDivision(910110, 'Home');
        $this->seedDivision(910140, 'Focal Div', ['focal_person' => 910502]);
        $this->seedDivision(910150, 'Risk Focal Div', ['risk_focal_person' => 910502]);
        $this->seedDivision(910160, 'Director Div', ['director_id' => 910502]);
        $this->seedContract(910502, 910110, []);

        $ids = StaffDivisionContext::switchableIds(910502);

        $this->assertContains(910110, $ids);
        $this->assertContains(910140, $ids);
        $this->assertContains(910150, $ids);
        $this->assertContains(910160, $ids);
    }

    public function test_set_active_updates_cache_token_and_resolve_prefers_active(): void
    {
        $this->seedDivision(910310, 'P');
        $this->seedDivision(910320, 'A');
        $this->seedContract(910504, 910310, [910320]);

        $token = 'test-division-ctx-token';
        $payload = [
            'staff_id' => 910504,
            'division_id' => 910310,
            'api_token' => $token,
            'permissions' => [],
        ];
        Cache::put('risk_api_token:'.$token, $payload, now()->addHour());

        $request = Request::create('/api/v1/division-context', 'POST', ['division_id' => 910320]);
        $request->headers->set('Authorization', 'Bearer '.$token);
        $request->attributes->set('risk_staff_id', 910504);
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('risk_register', $payload);

        $this->assertTrue(StaffDivisionContext::setActiveOnSession($request, 910320));

        $cached = Cache::get('risk_api_token:'.$token);
        $this->assertIsArray($cached);
        $this->assertSame(910320, (int) ($cached[StaffDivisionContext::SESSION_ACTIVE_ID] ?? 0));
        $this->assertSame(910320, StaffDivisionContext::resolveDivisionId($cached, 910504));
    }

    public function test_set_active_rejects_outside_switchable_set(): void
    {
        $this->seedDivision(910210, 'Only');
        $this->seedContract(910503, 910210, []);

        $request = Request::create('/api/v1/division-context', 'POST', ['division_id' => 999999]);
        $request->attributes->set('risk_staff_id', 910503);
        $request->setLaravelSession($this->app['session']->driver());

        $this->assertFalse(StaffDivisionContext::setActiveOnSession($request, 999999));
    }

    public function test_payload_enabled_only_when_two_or_more_divisions(): void
    {
        $this->seedDivision(910410, 'Only');
        $this->seedContract(910505, 910410, []);

        $payload = StaffDivisionContext::payloadFor(910505, 910410);
        $this->assertFalse($payload['enabled']);

        $this->seedDivision(910420, 'Second', ['focal_person' => 910505]);
        $payload = StaffDivisionContext::payloadFor(910505, 910410);
        $this->assertTrue($payload['enabled']);
        $this->assertCount(2, $payload['divisions']);
    }
}
