<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Modules\Auth\Http\Controllers\Api\PortalSpaAuthController;
use Modules\Auth\Models\PortalUser;
use Tests\TestCase;

class OAuthUserApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedFixtures();
    }

    public function test_oauth_user_endpoint_returns_the_authenticated_portal_user(): void
    {
        $user = PortalUser::query()->findOrFail(10);

        Passport::actingAs($user, ['*']);

        $matchedRoute = app('router')->getRoutes()->match(Request::create('/api/v1/oauth/user', 'GET'));
        $request = Request::create('/api/v1/oauth/user', 'GET');
        $request->setUserResolver(fn (): PortalUser => $user);

        $response = app(PortalSpaAuthController::class)->me($request);
        $payload = $response->getData(true);

        $this->assertSame('api/v1/oauth/user', $matchedRoute->uri());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(10, $payload['data']['id']);
        $this->assertSame('alice.oauth@example.test', $payload['data']['email']);
        $this->assertSame(100, $payload['data']['profile']['staff_id']);
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->integer('auth_staff_id')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(0);
            $table->integer('allow_email_login')->default(0);
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->integer('staff_id')->index();
            $table->integer('division_id')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 100,
            'fname' => 'Alice',
            'lname' => 'OAuth',
            'work_email' => 'alice.oauth@example.test',
        ]);

        DB::table('user')->insert([
            'user_id' => 10,
            'auth_staff_id' => 100,
            'name' => 'Alice OAuth',
            'password' => bcrypt('password'),
            'role' => 17,
            'status' => 1,
            'allow_email_login' => 1,
        ]);
    }
}
