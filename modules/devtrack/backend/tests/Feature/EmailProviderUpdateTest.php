<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailProviderUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_update_without_password_keeps_existing_secret(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $create = $this->withToken($token)->postJson('/api/v1/admin/email-providers', [
            'name' => 'SMTP',
            'driver' => EmailDriver::Smtp->value,
            'from_address' => 'noreply@example.com',
            'is_default' => true,
            'is_active' => true,
            'config' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'mailer',
                'password' => 'keep-me-secret',
            ],
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->withToken($token)->putJson("/api/v1/admin/email-providers/{$id}", [
            'name' => 'SMTP Updated',
            'driver' => EmailDriver::Smtp->value,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Mailer',
            'is_active' => true,
            'is_default' => true,
            'priority' => 50,
            'description' => '',
            'config' => [
                'host' => 'smtp.example.com',
                'port' => '587',
                'encryption' => 'tls',
                'username' => 'mailer',
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'SMTP Updated')
            ->assertJsonPath('data.config_secrets.password', true)
            ->assertJsonMissingPath('data.config.password');

        $this->assertSame(
            'keep-me-secret',
            EmailProvider::query()->findOrFail($id)->safeConfig()['password'] ?? null
        );
    }

    public function test_update_succeeds_when_stored_config_ciphertext_is_corrupt(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $provider = EmailProvider::query()->create([
            'name' => 'SMTP',
            'slug' => 'smtp-corrupt',
            'driver' => EmailDriver::Smtp,
            'from_address' => 'noreply@example.com',
            'is_default' => true,
            'is_active' => true,
            'config' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'password' => 'old-password',
            ],
        ]);

        // Simulate APP_KEY rotation: ciphertext the current key cannot decrypt.
        DB::table('email_providers')->where('id', $provider->id)->update([
            'config' => 'eyJpdiI6ImJhZCIsInZhbHVlIjoiYmFkIiwibWFjIjoiYmFkIn0=',
        ]);

        $provider->refresh();
        $this->assertFalse($provider->configIsReadable());

        $update = $this->withToken($token)->putJson("/api/v1/admin/email-providers/{$provider->id}", [
            'name' => 'SMTP Recovered',
            'driver' => EmailDriver::Smtp->value,
            'from_address' => 'noreply@example.com',
            'is_active' => true,
            'is_default' => true,
            'priority' => 10,
            'config' => [
                'host' => 'smtp.recovered.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'mailer',
                'password' => 'new-password',
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.name', 'SMTP Recovered')
            ->assertJsonPath('data.config.host', 'smtp.recovered.example.com')
            ->assertJsonPath('data.config_secrets.password', true)
            ->assertJsonPath('data.config_corrupt', false);

        $this->assertSame(
            'new-password',
            EmailProvider::query()->findOrFail($provider->id)->safeConfig()['password'] ?? null
        );
    }

    public function test_admin_can_disable_non_default_provider_but_not_delete(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        EmailProvider::factory()->create([
            'name' => 'Default',
            'is_default' => true,
            'is_active' => true,
        ]);
        $secondary = EmailProvider::factory()->create([
            'name' => 'Secondary',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/email-providers/'.$secondary->id)
            ->assertMethodNotAllowed();

        $this->withToken($token)
            ->putJson('/api/v1/admin/email-providers/'.$secondary->id, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse((bool) $secondary->fresh()->is_active);
    }

    public function test_admin_cannot_disable_default_provider(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $default = EmailProvider::factory()->create([
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->putJson('/api/v1/admin/email-providers/'.$default->id, ['is_active' => false])
            ->assertStatus(422);

        $this->assertTrue((bool) $default->fresh()->is_active);
    }
}
