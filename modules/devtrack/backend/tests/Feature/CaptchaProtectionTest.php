<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailProvider;
use App\Models\User;
use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaptchaProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function enableCaptcha(): void
    {
        config([
            'services.captcha.enabled' => true,
            'services.captcha.length' => 5,
            'services.captcha.ttl' => 600,
        ]);
    }

    public function test_captcha_endpoint_returns_image_challenge_when_enabled(): void
    {
        $this->enableCaptcha();

        $this->getJson('/api/v1/admin/captcha')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['key', 'image', 'ttl']]);
    }

    public function test_login_requires_valid_captcha_when_enabled(): void
    {
        $this->enableCaptcha();

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('CorrectPass1!'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'CorrectPass1!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'CorrectPass1!',
            'captcha_key' => 'missing-key',
            'captcha' => 'wrong',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);
    }

    public function test_login_succeeds_with_valid_captcha(): void
    {
        $this->enableCaptcha();

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('CorrectPass1!'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        app(CaptchaService::class)->seedForTests('login-key', 'XY9Z1');

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'CorrectPass1!',
            'captcha_key' => 'login-key',
            'captcha' => 'xy9z1',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_send_mail_requires_valid_captcha_when_enabled(): void
    {
        $this->enableCaptcha();

        EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/send-mail', [
                'to' => 'recipient@example.com',
                'subject' => 'Manual test',
                'body' => '<p>Hello</p>',
                'captcha_key' => 'missing-key',
                'captcha' => 'wrong',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);
    }

    public function test_send_mail_succeeds_with_valid_captcha(): void
    {
        $this->enableCaptcha();

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        Queue::fake();

        app(CaptchaService::class)->seedForTests('test-key', 'AB12C');

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/send-mail', [
                'to' => 'recipient@example.com',
                'subject' => 'Manual test',
                'body' => '<p>Hello</p>',
                'provider_id' => $provider->id,
                'captcha_key' => 'test-key',
                'captcha' => 'ab12c',
            ])
            ->assertOk();

        Queue::assertPushed(SendEmailJob::class);
    }

    public function test_provider_test_requires_captcha_when_enabled(): void
    {
        $this->enableCaptcha();

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/email-providers/'.$provider->id.'/test', [
                'to' => 'recipient@example.com',
                'captcha_key' => 'bad-key',
                'captcha' => 'nope',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);
    }
}
