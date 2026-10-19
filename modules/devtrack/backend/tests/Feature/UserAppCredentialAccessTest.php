<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAppCredentialAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EmailProvider, 1: ExternalIntegration, 2: ExternalIntegration}
     */
    private function seedProviderAndApps(): array
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $apm = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm',
            'api_key_hash' => ExternalIntegration::hashClientSecret('SecretApm2026!!'),
            'api_key_prefix' => ExternalIntegration::clientSecretHint('SecretApm2026!!'),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $helpdesk = ExternalIntegration::query()->create([
            'name' => 'Helpdesk',
            'slug' => 'helpdesk',
            'api_key_hash' => ExternalIntegration::hashClientSecret('SecretHd2026!!!'),
            'api_key_prefix' => ExternalIntegration::clientSecretHint('SecretHd2026!!!'),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        return [$provider, $apm, $helpdesk];
    }

    public function test_admin_can_assign_multiple_app_credentials_to_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        [, $apm, $helpdesk] = $this->seedProviderAndApps();
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/users', [
            'name' => 'App Viewer',
            'email' => 'viewer@emailserver.local',
            'password' => 'SecurePass123!',
            'is_admin' => false,
            'is_active' => true,
            'external_integration_ids' => [$apm->id, $helpdesk->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonCount(2, 'data.external_integration_ids')
            ->assertJsonCount(2, 'data.external_integrations');

        $user = User::query()->where('email', 'viewer@emailserver.local')->first();
        $this->assertNotNull($user);
        $this->assertEqualsCanonicalizing(
            [$apm->id, $helpdesk->id],
            $user->externalIntegrations()->pluck('external_integrations.id')->all()
        );
    }

    public function test_admin_assignment_clears_when_user_promoted_to_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        [, $apm] = $this->seedProviderAndApps();
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $user->externalIntegrations()->sync([$apm->id]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->putJson('/api/v1/admin/users/'.$user->id, [
                'is_admin' => true,
                'external_integration_ids' => [$apm->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonCount(0, 'data.external_integration_ids');

        $this->assertCount(0, $user->fresh()->externalIntegrations);
    }

    public function test_non_admin_only_sees_logs_for_assigned_apps(): void
    {
        [$provider, $apm, $helpdesk] = $this->seedProviderAndApps();

        $viewer = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'password' => Hash::make('SecurePass123!'),
        ]);
        $viewer->externalIntegrations()->sync([$apm->id]);

        $apmLog = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $apm->id,
            'to' => 'a@example.com',
            'subject' => 'APM mail',
            'status' => 'sent',
            'driver' => $provider->driver->value,
            'meta' => ['source' => 'integration'],
        ]);

        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $helpdesk->id,
            'to' => 'h@example.com',
            'subject' => 'Helpdesk mail',
            'status' => 'sent',
            'driver' => $provider->driver->value,
            'meta' => ['source' => 'integration'],
        ]);

        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => null,
            'to' => 'admin@example.com',
            'subject' => 'Internal mail',
            'status' => 'sent',
            'driver' => $provider->driver->value,
            'meta' => ['source' => 'admin'],
        ]);

        $token = $viewer->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/email-logs')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $apmLog->id);

        $filters = $this->withToken($token)
            ->getJson('/api/v1/admin/email-logs/filter-options')
            ->assertOk();

        $filters->assertJsonPath('data.can_view_internal', false)
            ->assertJsonCount(1, 'data.clients')
            ->assertJsonPath('data.clients.0.id', $apm->id);
    }

    public function test_admin_sees_all_logs_by_default(): void
    {
        [$provider, $apm, $helpdesk] = $this->seedProviderAndApps();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $apm->id,
            'to' => 'a@example.com',
            'subject' => 'APM',
            'status' => 'sent',
            'driver' => $provider->driver->value,
        ]);
        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $helpdesk->id,
            'to' => 'h@example.com',
            'subject' => 'HD',
            'status' => 'sent',
            'driver' => $provider->driver->value,
        ]);
        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'i@example.com',
            'subject' => 'Internal',
            'status' => 'sent',
            'driver' => $provider->driver->value,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/email-logs')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_non_admin_cannot_filter_unassigned_app(): void
    {
        [$provider, $apm, $helpdesk] = $this->seedProviderAndApps();
        $viewer = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $viewer->externalIntegrations()->sync([$apm->id]);

        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $helpdesk->id,
            'to' => 'h@example.com',
            'subject' => 'HD',
            'status' => 'sent',
            'driver' => $provider->driver->value,
        ]);

        $this->withToken($viewer->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/email-logs?external_integration_id='.$helpdesk->id)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
