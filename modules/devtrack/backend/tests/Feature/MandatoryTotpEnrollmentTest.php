<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MandatoryTotpEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.captcha.enabled' => false]);
    }

    public function test_admin_created_user_is_marked_totp_required(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/users', [
                'name' => 'New Staff',
                'email' => 'new.staff@example.com',
                'password' => 'SecurePass1!',
                'is_admin' => false,
                'is_active' => true,
                'external_integration_ids' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('data.totp_required', true)
            ->assertJsonPath('data.must_setup_totp', true);

        $this->assertDatabaseHas('users', [
            'email' => 'new.staff@example.com',
            'totp_required' => 1,
            'two_factor_totp_enabled' => 0,
        ]);
    }

    public function test_login_reports_must_setup_totp_for_required_accounts(): void
    {
        User::factory()->create([
            'email' => 'new.staff@example.com',
            'password' => Hash::make('SecurePass1!'),
            'is_admin' => false,
            'is_active' => true,
            'totp_required' => true,
            'two_factor_totp_enabled' => false,
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'new.staff@example.com',
            'password' => 'SecurePass1!',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'new.staff@example.com')
            ->assertJsonPath('user.must_setup_totp', true)
            ->assertJsonPath('user.totp_required', true);
    }

    public function test_required_user_is_blocked_until_authenticator_is_confirmed(): void
    {
        $user = User::factory()->create([
            'email' => 'enroll@example.com',
            'password' => Hash::make('SecurePass1!'),
            'is_admin' => true,
            'is_active' => true,
            'totp_required' => true,
            'two_factor_totp_enabled' => false,
        ]);

        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('must_setup_totp', true);

        $setup = $this->withToken($token)
            ->postJson('/api/v1/admin/auth/2fa/totp/setup', [])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url']]);

        $secret = (string) $setup->json('data.secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/2fa/totp/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.two_factor_totp_enabled', true)
            ->assertJsonPath('data.must_setup_totp', false)
            ->assertJsonPath('data.totp_required', true);

        $user->refresh();
        $this->assertTrue($user->two_factor_totp_enabled);
        $this->assertFalse($user->mustSetupTotp());

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/2fa/totp/disable', ['password' => 'SecurePass1!'])
            ->assertStatus(422);
    }

    public function test_seeded_users_without_totp_required_can_access_without_enrollment(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => Hash::make('SecurePass1!'),
            'is_admin' => true,
            'is_active' => true,
            'totp_required' => false,
        ]);

        $login = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $user->email,
            'password' => 'SecurePass1!',
        ])->assertOk();

        $this->assertFalse((bool) $login->json('user.must_setup_totp'));

        $this->withToken((string) $login->json('token'))
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();
    }
}
