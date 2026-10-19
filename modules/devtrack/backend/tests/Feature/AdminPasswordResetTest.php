<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_forgot_password_returns_generic_message_and_queues_email_for_active_user(): void
    {
        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'andrewa@africacdcorg',
            'is_admin' => true,
            'is_active' => true,
        ]);

        Queue::fake();

        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'andrewa@africacdcorg',
        ])->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Queue::assertPushed(SendEmailJob::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'andrewa@africacdcorg']);
    }

    public function test_forgot_password_does_not_reveal_missing_accounts(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Queue::assertNothingPushed();
    }

    public function test_forgot_password_reset_link_uses_trusted_frontend_host(): void
    {
        config([
            'app.url' => 'https://notifications.africacdc.org',
            'app.frontend_url' => 'https://attacker.example',
        ]);

        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'andrewa@africacdcorg',
            'is_admin' => true,
            'is_active' => true,
        ]);

        Queue::fake();

        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'andrewa@africacdcorg',
        ])->assertOk();

        Queue::assertPushed(SendEmailJob::class, function (SendEmailJob $job): bool {
            $log = \App\Models\EmailLog::query()->find($job->emailLogId);
            $body = (string) ($log?->meta['body'] ?? '');

            return str_contains($body, 'https://notifications.africacdc.org/reset-password')
                && ! str_contains($body, 'attacker.example');
        });
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'andrewa@africacdcorg',
            'password' => Hash::make('OldPassword1!'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $user->createToken('admin-panel')->plainTextToken;

        $this->postJson('/api/v1/admin/auth/reset-password', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass1!', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
