<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ShareAuthLoginApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.url' => 'http://localhost',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'share.jwt_ttl' => 3600,
            'share.jwt_audience' => 'share-api',
        ]);
        URL::forceRootUrl('http://localhost');
        putenv('JWT_SECRET=test-share-jwt-secret');
        $_ENV['JWT_SECRET'] = 'test-share-jwt-secret';
        $_SERVER['JWT_SECRET'] = 'test-share-jwt-secret';

        DB::purge();
        DB::reconnect();

        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
            $table->string('photo')->nullable();
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('password')->nullable();
            $table->string('name')->nullable();
            $table->integer('role')->default(17);
            $table->integer('auth_staff_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('langauge')->nullable();
        });

        DB::table('staff')->insert([
            'staff_id' => 42,
            'fname' => 'Ada',
            'lname' => 'Lovelace',
            'work_email' => 'ada@africacdc.org',
            'photo' => null,
        ]);
        DB::table('user')->insert([
            'user_id' => 7,
            'password' => password_hash('secret-pass', PASSWORD_BCRYPT),
            'name' => 'Ada Lovelace',
            'role' => 17,
            'auth_staff_id' => 42,
            'status' => 1,
            'langauge' => 'en',
        ]);
    }

    public function test_login_issues_jwt_from_json_username_and_password(): void
    {
        $response = $this->postJson('/share/login', [
            'username' => 'ada@africacdc.org',
            'password' => 'secret-pass',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('aud', 'share-api')
            ->assertJsonPath('user.staff_id', 42)
            ->assertJsonPath('user.email', 'ada@africacdc.org');

        $token = (string) $response->json('access_token');
        $this->assertNotSame('', $token);
        $this->assertSame($token, (string) $response->json('token'));
        $this->assertGreaterThan(0, (int) $response->json('expires_in'));
    }

    public function test_login_accepts_email_alias(): void
    {
        $this->postJson('/share/login', [
            'email' => 'ada@africacdc.org',
            'password' => 'secret-pass',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->postJson('/share/login', [
            'username' => 'ada@africacdc.org',
            'password' => 'wrong',
        ])->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_login_requires_credentials(): void
    {
        $this->postJson('/share/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_legacy_token_still_works_with_http_basic(): void
    {
        $response = $this->withBasicAuth('ada@africacdc.org', 'secret-pass')
            ->postJson('/share/token');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token_type', 'Bearer');
        $this->assertNotSame('', (string) $response->json('access_token'));
    }

    public function test_bearer_from_login_authorizes_share_route(): void
    {
        Schema::create('divisions', function (Blueprint $table): void {
            $table->increments('division_id');
            $table->string('division_name')->nullable();
        });
        DB::table('divisions')->insert([
            'division_id' => 1,
            'division_name' => 'HQ',
        ]);

        $token = (string) $this->postJson('/share/login', [
            'username' => 'ada@africacdc.org',
            'password' => 'secret-pass',
        ])->json('access_token');

        $this->withToken($token)
            ->getJson('/share/divisions')
            ->assertOk()
            ->assertJsonFragment(['division_name' => 'HQ']);
    }
}
