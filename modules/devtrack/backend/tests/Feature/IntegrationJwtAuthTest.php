<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IntegrationJwtAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_can_exchange_credentials_for_jwt_and_send_mail(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $tokenResponse = $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => $integration->slug,
            'client_secret' => $clientSecret,
        ]);

        $tokenResponse->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_in', 'expires_at', 'integration']);

        $jwt = $tokenResponse->json('token');

        Queue::fake();

        $sendResponse = $this->withToken($jwt)->postJson('/api/v1/integrations/send', [
            'to' => 'user@example.com',
            'subject' => 'JWT test',
            'body' => '<p>Hello</p>',
            'is_html' => true,
        ]);

        $sendResponse->assertOk()->assertJsonPath('status', 'pending');
        Queue::assertPushed(SendEmailJob::class);

        $log = \App\Models\EmailLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('127.0.0.1', $log->meta['sender_ip'] ?? null);
        $this->assertSame('127.0.0.1', $log->toLogArray()['sender_ip'] ?? null);
    }

    public function test_integration_send_accepts_swagger_form_style_payload(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm-form',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $jwt = $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => $integration->slug,
            'client_secret' => $clientSecret,
        ])->json('token');

        Queue::fake();

        $this->withToken($jwt)
            ->post('/api/v1/integrations/send', [
                'to' => 'agabaandre@gmail.com',
                'subject' => 'Welcome to the portal',
                'body' => '<p>Hello from Email Server</p>',
                'is_html' => 'true',
                'provider_id' => '0',
                'cc' => 'cc',
                'bcc' => 'bcc',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_expired_jwt_is_rejected(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm-expired',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'is_active' => true,
        ]);

        $secret = config('integration.jwt_secret');
        $jwt = \Firebase\JWT\JWT::encode([
            'sub' => (string) $integration->id,
            'slug' => $integration->slug,
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ], $secret, 'HS256');

        $this->withToken($jwt)
            ->postJson('/api/v1/integrations/send', [
                'to' => 'user@example.com',
                'subject' => 'Should fail',
                'body' => 'x',
            ])
            ->assertUnauthorized();
    }

    public function test_wrong_client_secret_is_rejected(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm-wrong',
            'api_key_hash' => ExternalIntegration::hashClientSecret('IntegrationSecret2026!'),
            'api_key_prefix' => 'Inte••••',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => 'apm-wrong',
            'client_secret' => 'WrongSecret1234567',
        ])->assertUnauthorized();
    }
}
