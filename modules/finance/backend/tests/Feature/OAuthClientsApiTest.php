<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Http\Controllers\Api\V1\OAuthClientApiController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OAuthClientsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->put('user.permissions', [17]);

        $this->createTables();
    }

    public function test_index_returns_only_active_non_personal_clients(): void
    {
        $this->insertClient([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Staff Mobile',
            'secret' => 'hashed-secret',
            'redirect_uris' => json_encode(['https://mobile.example.test/callback'], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['authorization_code', 'refresh_token'], JSON_THROW_ON_ERROR),
            'revoked' => false,
        ]);
        $this->insertClient([
            'id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Personal Access Client',
            'secret' => 'hashed-secret',
            'redirect_uris' => json_encode([], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['personal_access'], JSON_THROW_ON_ERROR),
            'revoked' => false,
            'personal_access_client' => true,
        ]);
        $this->insertClient([
            'id' => '33333333-3333-3333-3333-333333333333',
            'name' => 'Revoked Client',
            'secret' => 'hashed-secret',
            'redirect_uris' => json_encode(['https://revoked.example.test/callback'], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['authorization_code', 'refresh_token'], JSON_THROW_ON_ERROR),
            'revoked' => true,
        ]);
        $this->insertClient([
            'id' => '44444444-4444-4444-4444-444444444444',
            'name' => 'Public SPA',
            'secret' => null,
            'redirect_uris' => json_encode(['https://spa.example.test/callback'], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['authorization_code', 'refresh_token'], JSON_THROW_ON_ERROR),
            'revoked' => false,
        ]);

        $response = app(OAuthClientApiController::class)->index(
            Request::create('/api/v1/auth/oauth-clients', 'GET')
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            '44444444-4444-4444-4444-444444444444',
            '11111111-1111-1111-1111-111111111111',
        ], array_column($payload['data'], 'id'));
        $this->assertSame(['https://spa.example.test/callback'], $payload['data'][0]['redirect_uris']);
        $this->assertTrue($payload['data'][0]['public']);
        $this->assertFalse($payload['data'][1]['public']);
    }

    public function test_store_creates_confidential_client_and_returns_plain_secret_once(): void
    {
        $response = app(OAuthClientApiController::class)->store(Request::create(
            '/api/v1/auth/oauth-clients',
            'POST',
            [
                'name' => 'Helpdesk Web',
                'redirect_uris' => [
                    'https://helpdesk.example.test/oauth/callback',
                    'https://helpdesk.example.test/oauth/complete',
                ],
                'public' => false,
            ]
        ));

        $payload = $response->getData(true);
        $clientId = $payload['data']['id'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Helpdesk Web', $payload['data']['name']);
        $this->assertFalse($payload['data']['public']);
        $this->assertSame([
            'https://helpdesk.example.test/oauth/callback',
            'https://helpdesk.example.test/oauth/complete',
        ], $payload['data']['redirect_uris']);
        $this->assertIsString($payload['data']['plain_secret']);
        $this->assertNotEmpty($payload['data']['plain_secret']);
        $this->assertNull($payload['data']['secret'] ?? null);
        $this->assertNotNull(DB::table('oauth_clients')->where('id', $clientId)->value('secret'));
    }

    public function test_store_creates_public_client_without_secret(): void
    {
        $response = app(OAuthClientApiController::class)->store(Request::create(
            '/api/v1/auth/oauth-clients',
            'POST',
            [
                'name' => 'Staff SPA',
                'redirect_uris' => ['https://staff.example.test/auth/callback'],
                'public' => true,
            ]
        ));

        $payload = $response->getData(true);
        $clientId = $payload['data']['id'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['data']['public']);
        $this->assertNull($payload['data']['plain_secret'] ?? null);
        $this->assertNull(DB::table('oauth_clients')->where('id', $clientId)->value('secret'));
    }

    public function test_update_replaces_name_and_multiple_redirect_uris(): void
    {
        $this->insertClient([
            'id' => '66666666-6666-6666-6666-666666666666',
            'name' => 'Legacy App',
            'secret' => null,
            'redirect_uris' => json_encode(['https://legacy.example.test/callback'], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['authorization_code', 'refresh_token'], JSON_THROW_ON_ERROR),
            'revoked' => false,
        ]);

        $response = app(OAuthClientApiController::class)->update(
            Request::create(
                '/api/v1/auth/oauth-clients/66666666-6666-6666-6666-666666666666',
                'PUT',
                [
                    'name' => 'Legacy App Updated',
                    'redirect_uris' => [
                        'https://legacy.example.test/oauth/callback',
                        'https://legacy.example.test/oauth/complete',
                        'http://localhost:5173/auth/callback',
                    ],
                ]
            ),
            '66666666-6666-6666-6666-666666666666',
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Legacy App Updated', $payload['data']['name']);
        $this->assertSame([
            'https://legacy.example.test/oauth/callback',
            'https://legacy.example.test/oauth/complete',
            'http://localhost:5173/auth/callback',
        ], $payload['data']['redirect_uris']);

        $stored = json_decode(
            (string) DB::table('oauth_clients')->where('id', '66666666-6666-6666-6666-666666666666')->value('redirect_uris'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertSame($payload['data']['redirect_uris'], $stored);
    }

    public function test_store_requires_permission_17(): void
    {
        session()->put('user.permissions', [15]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You do not have permission for this action.');

        try {
            app(OAuthClientApiController::class)->store(Request::create(
                '/api/v1/auth/oauth-clients',
                'POST',
                [
                    'name' => 'Blocked Client',
                    'redirect_uris' => ['https://blocked.example.test/callback'],
                    'public' => true,
                ]
            ));
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    public function test_destroy_revokes_client_and_related_tokens(): void
    {
        $this->insertClient([
            'id' => '55555555-5555-5555-5555-555555555555',
            'name' => 'Legacy Integrator',
            'secret' => 'hashed-secret',
            'redirect_uris' => json_encode(['https://legacy.example.test/callback'], JSON_THROW_ON_ERROR),
            'grant_types' => json_encode(['authorization_code', 'refresh_token'], JSON_THROW_ON_ERROR),
            'revoked' => false,
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => 'token-1',
            'user_id' => null,
            'client_id' => '55555555-5555-5555-5555-555555555555',
            'name' => null,
            'scopes' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => 'refresh-1',
            'access_token_id' => 'token-1',
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $response = app(OAuthClientApiController::class)->destroy(
            '55555555-5555-5555-5555-555555555555',
            Request::create('/api/v1/auth/oauth-clients/55555555-5555-5555-5555-555555555555', 'DELETE')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $response->getData(true)['ok']);
        $this->assertSame(1, (int) DB::table('oauth_clients')->where('id', '55555555-5555-5555-5555-555555555555')->value('revoked'));
        $this->assertSame(1, (int) DB::table('oauth_access_tokens')->where('id', 'token-1')->value('revoked'));
        $this->assertSame(1, (int) DB::table('oauth_refresh_tokens')->where('id', 'refresh-1')->value('revoked'));
    }

    public function test_oauth_client_admin_routes_are_registered(): void
    {
        $listRoute = app('router')->getRoutes()->match(Request::create('/api/v1/auth/oauth-clients', 'GET'));
        $storeRoute = app('router')->getRoutes()->match(Request::create('/api/v1/auth/oauth-clients', 'POST'));
        $updateRoute = app('router')->getRoutes()->match(
            Request::create('/api/v1/auth/oauth-clients/11111111-1111-1111-1111-111111111111', 'PUT')
        );
        $destroyRoute = app('router')->getRoutes()->match(
            Request::create('/api/v1/auth/oauth-clients/11111111-1111-1111-1111-111111111111', 'DELETE')
        );

        $this->assertSame('api/v1/auth/oauth-clients', $listRoute->uri());
        $this->assertSame('api/v1/auth/oauth-clients', $storeRoute->uri());
        $this->assertSame('api/v1/auth/oauth-clients/{id}', $updateRoute->uri());
        $this->assertSame('api/v1/auth/oauth-clients/{id}', $destroyRoute->uri());
    }

    protected function createTables(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('personal_access_client')->default(false);
            $table->boolean('password_client')->default(false);
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->foreignUuid('client_id');
            $table->foreignId('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->char('access_token_id', 80)->index();
            $table->boolean('revoked')->default(false);
            $table->dateTime('expires_at')->nullable();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertClient(array $attributes): void
    {
        DB::table('oauth_clients')->insert(array_merge([
            'owner_type' => null,
            'owner_id' => null,
            'provider' => null,
            'personal_access_client' => false,
            'password_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
