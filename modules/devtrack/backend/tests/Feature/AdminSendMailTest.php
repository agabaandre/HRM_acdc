<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminSendMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.captcha.enabled' => false]);
    }

    public function test_authenticated_admin_can_queue_manual_email(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        Queue::fake();

        $this->withToken($token)->postJson('/api/v1/admin/send-mail', [
            'to' => 'recipient@example.com',
            'subject' => 'Manual test',
            'body' => '<p>Hello from admin panel</p>',
            'provider_id' => $provider->id,
        ])->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonStructure(['log_id', 'message']);

        Queue::assertPushed(SendEmailJob::class);
    }

    public function test_admin_can_queue_email_with_attachment(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log-attach',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        Queue::fake();

        $this->withToken($token)->postJson('/api/v1/admin/send-mail', [
            'to' => 'recipient@example.com',
            'subject' => 'With file',
            'body' => '<p>See attached</p>',
            'provider_id' => $provider->id,
            'attachments' => [
                [
                    'filename' => 'note.txt',
                    'content' => base64_encode('hello'),
                    'content_type' => 'text/plain',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('attachment_count', 1);

        Queue::assertPushed(SendEmailJob::class);
    }

    public function test_guest_cannot_send_manual_email(): void
    {
        $this->postJson('/api/v1/admin/send-mail', [
            'to' => 'recipient@example.com',
            'subject' => 'Manual test',
            'body' => 'Hello',
        ])->assertUnauthorized();
    }
}
