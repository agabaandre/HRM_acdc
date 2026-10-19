<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_client_token_auth_is_logged_as_client(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'Staff Portal',
            'slug' => 'staff-portal',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => $integration->slug,
            'client_secret' => $clientSecret,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'client_auth_success',
            'actor_type' => 'client',
            'external_integration_id' => $integration->id,
            'user_email' => 'staff-portal',
        ]);
    }

    public function test_failed_client_token_auth_is_logged_as_client(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => 'unknown-client',
            'client_secret' => 'wrong-secret-value-here!!',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'client_auth_failed',
            'actor_type' => 'client',
            'user_email' => 'unknown-client',
        ]);
    }

    public function test_audit_logs_can_be_filtered_by_actor_type(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $clientSecret = 'IntegrationSecret2026!';
        ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => 'apm',
            'client_secret' => $clientSecret,
        ])->assertOk();

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs?actor_type=client')
            ->assertOk()
            ->assertJsonPath('data.0.actor_type', 'client')
            ->assertJsonPath('data.0.actor_label', 'Client');

        $this->assertTrue(
            AuditLog::query()->where('actor_type', 'client')->exists()
        );
    }
}
