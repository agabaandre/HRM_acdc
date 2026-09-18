<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Modules\Auth\Http\Controllers\OidcDiscoveryController;
use Tests\TestCase;

class OidcDiscoveryTest extends TestCase
{
    private string $oauthPublicKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost/staff/staff-portal/backend']);

        $this->oauthPublicKeyPath = storage_path('oauth-public.key');
        @mkdir(dirname($this->oauthPublicKeyPath), 0775, true);

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $details = is_resource($key) || $key instanceof \OpenSSLAsymmetricKey
            ? openssl_pkey_get_details($key)
            : false;

        $this->assertIsArray($details);
        file_put_contents($this->oauthPublicKeyPath, $details['key']);
    }

    protected function tearDown(): void
    {
        @unlink($this->oauthPublicKeyPath);

        parent::tearDown();
    }

    public function test_oidc_discovery_routes_are_registered(): void
    {
        $rootDiscovery = app('router')->getRoutes()->match(
            Request::create('/.well-known/openid-configuration', 'GET')
        );
        $oauthDiscovery = app('router')->getRoutes()->match(
            Request::create('/oauth/.well-known/openid-configuration', 'GET')
        );
        $jwks = app('router')->getRoutes()->match(
            Request::create('/oauth/jwks', 'GET')
        );

        $this->assertSame('.well-known/openid-configuration', $rootDiscovery->uri());
        $this->assertSame('oauth/.well-known/openid-configuration', $oauthDiscovery->uri());
        $this->assertSame('oauth/jwks', $jwks->uri());
    }

    public function test_oidc_discovery_response_exposes_oauth_and_jwks_urls(): void
    {
        $response = app(OidcDiscoveryController::class)->discovery();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'issuer' => 'http://localhost/staff/staff-portal/backend',
            'authorization_endpoint' => 'http://localhost/staff/staff-portal/backend/oauth/authorize',
            'token_endpoint' => 'http://localhost/staff/staff-portal/backend/oauth/token',
            'userinfo_endpoint' => 'http://localhost/staff/staff-portal/backend/api/v1/oauth/user',
            'jwks_uri' => 'http://localhost/staff/staff-portal/backend/oauth/jwks',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ], $response->getData(true));
    }

    public function test_jwks_endpoint_returns_an_rsa_signing_key(): void
    {
        $response = app(OidcDiscoveryController::class)->jwks();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('RSA', $payload['keys'][0]['kty'] ?? null);
        $this->assertSame('sig', $payload['keys'][0]['use'] ?? null);
        $this->assertSame('RS256', $payload['keys'][0]['alg'] ?? null);
        $this->assertIsString($payload['keys'][0]['kid'] ?? null);
        $this->assertIsString($payload['keys'][0]['n'] ?? null);
        $this->assertIsString($payload['keys'][0]['e'] ?? null);
    }
}
