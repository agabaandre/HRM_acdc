<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\User;
use App\Services\PhpMailerSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SmtpPhpMailerDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_delivery_uses_phpmailer_not_laravel_mail(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP Fallback',
            'slug' => 'fallback-smtp',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'secret',
            ],
            'from_address' => 'noreply@example.com',
            'from_name' => 'Mailer',
            'is_default' => true,
            'is_active' => true,
        ]);

        $mock = Mockery::mock(PhpMailerSmtpMailer::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (
                EmailProvider $p,
                string $to,
                string $subject,
                string $body,
                bool $isHtml,
            ) use ($provider) {
                return $p->id === $provider->id
                    && $to === 'recipient@example.com'
                    && $subject === 'Hello'
                    && str_contains($body, 'queued body')
                    && $isHtml === true;
            })
            ->andReturnNull();
        $this->app->instance(PhpMailerSmtpMailer::class, $mock);

        $log = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'status' => 'pending',
            'driver' => EmailDriver::Smtp->value,
            'meta' => [
                'body' => '<p>queued body</p>',
                'is_html' => true,
                'cc' => [],
                'bcc' => [],
            ],
        ]);

        $job = new SendEmailJob($log->id);
        $job->handle(app(\App\Services\EmailDispatchService::class));

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_admin_can_queue_smtp_email(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'SMTP Fallback',
            'slug' => 'fallback-smtp',
            'driver' => EmailDriver::Smtp,
            'config' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'secret',
            ],
            'from_address' => 'noreply@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        Queue::fake();

        $this->withToken($token)->postJson('/api/v1/admin/send-mail', [
            'to' => 'agabaandre@gmail.com',
            'subject' => 'Test SMTP',
            'body' => '<p>Hello via PHPMailer</p>',
            'provider_id' => $provider->id,
            'is_html' => true,
        ])->assertOk()
            ->assertJsonPath('status', 'pending');

        Queue::assertPushed(SendEmailJob::class);
    }
}
