<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_without_two_factor_returns_token_immediately(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonMissing(['requires_2fa']);
    }

    public function test_login_with_email_two_factor_requires_verification(): void
    {
        $dispatch = Mockery::mock(EmailDispatchService::class);
        $dispatch->shouldReceive('queue')->once();
        $this->app->instance(EmailDispatchService::class, $dispatch);

        $user = User::factory()->create([
            'email' => 'secure@example.com',
            'password' => Hash::make('Password123!'),
            'two_factor_email_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonStructure(['challenge_token', 'methods', 'email'])
            ->assertJsonMissing(['token']);
    }

    public function test_user_can_enable_and_disable_email_two_factor(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/2fa/email/enable', ['password' => 'Password123!'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_email_enabled', true);

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/2fa/email/disable', ['password' => 'Password123!'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_email_enabled', false);
    }
}
