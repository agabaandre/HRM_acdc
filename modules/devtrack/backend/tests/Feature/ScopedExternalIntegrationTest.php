<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Enums\UserApprovalStatus;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScopedExternalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.captcha.enabled' => false]);
    }

    private function approvedAccount(): User
    {
        return User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
            'totp_required' => false,
            'two_factor_totp_enabled' => true,
        ]);
    }

    public function test_approved_user_creates_inactive_owned_client(): void
    {
        $user = $this->approvedAccount();
        $token = $user->createToken('admin-panel')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/v1/admin/external-integrations', [
            'name' => 'My App',
            'generate_secret' => true,
        ])->assertCreated();

        $id = (int) $res->json('data.id');
        $this->assertFalse((bool) $res->json('data.is_active'));
        $this->assertTrue($user->externalIntegrations()->whereKey($id)->exists());
    }

    public function test_approved_user_without_totp_cannot_create_client(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
            'totp_required' => false,
            'two_factor_totp_enabled' => false,
        ]);

        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/external-integrations', [
                'name' => 'Blocked App',
                'generate_secret' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Enable authenticator app (2FA) in Security settings before registering a client.'
            );
    }

    public function test_user_cannot_see_others_clients(): void
    {
        $a = $this->approvedAccount();
        $b = $this->approvedAccount();

        $mine = ExternalIntegration::query()->create([
            'name' => 'Mine',
            'slug' => 'mine-app',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-mine-123456'),
            'api_key_prefix' => 'sec…',
            'is_active' => false,
        ]);
        $theirs = ExternalIntegration::query()->create([
            'name' => 'Theirs',
            'slug' => 'theirs-app',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-theirs-12345'),
            'api_key_prefix' => 'sec…',
            'is_active' => true,
        ]);
        $a->externalIntegrations()->attach($mine->id);
        $b->externalIntegrations()->attach($theirs->id);

        $this->withToken($a->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/external-integrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'mine-app');
    }

    public function test_non_admin_cannot_activate_own_client(): void
    {
        $user = $this->approvedAccount();
        $integration = ExternalIntegration::query()->create([
            'name' => 'Pending App',
            'slug' => 'pending-app',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-pending-1234'),
            'api_key_prefix' => 'sec…',
            'is_active' => false,
        ]);
        $user->externalIntegrations()->attach($integration->id);

        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->putJson('/api/v1/admin/external-integrations/'.$integration->id, [
                'name' => 'Pending App',
                'is_active' => true,
            ])
            ->assertOk();

        $this->assertFalse((bool) $integration->fresh()->is_active);
    }

    public function test_inactive_client_cannot_get_jwt(): void
    {
        $secret = 'inactive-client-secret-99';
        ExternalIntegration::query()->create([
            'name' => 'Inactive',
            'slug' => 'inactive-client',
            'api_key_hash' => ExternalIntegration::hashClientSecret($secret),
            'api_key_prefix' => 'ina…',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => 'inactive-client',
            'client_secret' => $secret,
        ])->assertUnauthorized();
    }

    public function test_approved_user_can_list_email_providers(): void
    {
        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = $this->approvedAccount();
        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/email-providers')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_disable_integration_and_delete_when_inactive(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $integration = ExternalIntegration::query()->create([
            'name' => 'Live App',
            'slug' => 'live-app',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-live-app-1234'),
            'api_key_prefix' => 'sec…',
            'is_active' => true,
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/external-integrations/'.$integration->id)
            ->assertStatus(422);

        $this->withToken($token)
            ->putJson('/api/v1/admin/external-integrations/'.$integration->id, [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse((bool) $integration->fresh()->is_active);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/external-integrations/'.$integration->id)
            ->assertOk();

        $this->assertDatabaseMissing('external_integrations', ['id' => $integration->id]);
    }

    public function test_activating_integration_notifies_linked_users(): void
    {
        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log-notify',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $owner = User::factory()->create([
            'email' => 'client-owner@example.com',
            'is_admin' => false,
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
        ]);
        $integration = ExternalIntegration::query()->create([
            'name' => 'Partner Client',
            'slug' => 'partner-client',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-partner-client-1'),
            'api_key_prefix' => 'sec…',
            'is_active' => false,
        ]);
        $owner->externalIntegrations()->attach($integration->id);

        Queue::fake();

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->putJson('/api/v1/admin/external-integrations/'.$integration->id, [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        Queue::assertPushed(SendEmailJob::class);
        $log = EmailLog::query()->where('to', 'client-owner@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('integration_approved', $log->meta['source'] ?? null);
        $this->assertStringContainsString('Partner Client', (string) ($log->meta['body'] ?? ''));
        $this->assertStringContainsString('/integrations', (string) ($log->meta['body'] ?? ''));
    }

    public function test_disabling_integration_notifies_linked_users(): void
    {
        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log-disable-notify',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $owner = User::factory()->create([
            'email' => 'client-disabled@example.com',
            'is_admin' => false,
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
        ]);
        $integration = ExternalIntegration::query()->create([
            'name' => 'Live Client',
            'slug' => 'live-client',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret-live-client-1'),
            'api_key_prefix' => 'sec…',
            'is_active' => true,
        ]);
        $owner->externalIntegrations()->attach($integration->id);

        Queue::fake();

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->putJson('/api/v1/admin/external-integrations/'.$integration->id, [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        Queue::assertPushed(SendEmailJob::class);
        $log = EmailLog::query()->where('to', 'client-disabled@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('integration_rejected', $log->meta['source'] ?? null);
        $this->assertStringContainsString('Live Client', (string) ($log->meta['body'] ?? ''));
    }
}
