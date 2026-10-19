<?php

namespace Tests\Feature;

use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailLogRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_logs_by_status_and_retry_failed(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => Hash::make('Secret123!'),
        ]);

        $provider = EmailProvider::factory()->create([
            'is_default' => true,
            'is_active' => true,
        ]);

        $failed = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'status' => 'failed',
            'driver' => $provider->driver->value,
            'error_message' => 'boom',
            'meta' => [
                'body' => '<p>Hello</p>',
                'is_html' => true,
                'source' => 'admin',
            ],
        ]);

        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'other@example.com',
            'subject' => 'Sent',
            'status' => 'sent',
            'driver' => $provider->driver->value,
            'meta' => ['body' => '<p>x</p>', 'is_html' => true],
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/email-logs?status=failed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $failed->id)
            ->assertJsonPath('data.0.can_retry', true);

        $this->withToken($token)
            ->getJson('/api/v1/admin/email-logs?driver='.$provider->driver->value)
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->withToken($token)
            ->getJson('/api/v1/admin/email-logs/filter-options')
            ->assertOk()
            ->assertJsonFragment(['value' => $provider->driver->value])
            ->assertJsonStructure([
                'data' => [
                    'statuses',
                    'drivers',
                    'clients',
                    'can_view_internal',
                ],
            ]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/email-logs/'.$failed->id.'/retry')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $job) => $job->emailLogId === $failed->id);

        $this->assertDatabaseHas('email_logs', [
            'id' => $failed->id,
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function test_admin_can_resend_all_failed(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $provider = EmailProvider::factory()->create([
            'is_default' => true,
            'is_active' => true,
        ]);

        $a = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'a@example.com',
            'subject' => 'A',
            'status' => 'failed',
            'driver' => $provider->driver->value,
            'meta' => ['body' => '<p>A</p>', 'is_html' => true],
        ]);
        $b = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'b@example.com',
            'subject' => 'B',
            'status' => 'failed',
            'driver' => $provider->driver->value,
            'meta' => ['body' => '<p>B</p>', 'is_html' => true],
        ]);
        EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'c@example.com',
            'subject' => 'C',
            'status' => 'failed',
            'driver' => $provider->driver->value,
            'meta' => ['source' => 'admin'],
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/email-logs/retry-failed')
            ->assertOk()
            ->assertJsonPath('queued', 2)
            ->assertJsonPath('skipped', 1);

        Queue::assertPushed(SendEmailJob::class, 2);
        $this->assertDatabaseHas('email_logs', ['id' => $a->id, 'status' => 'pending']);
        $this->assertDatabaseHas('email_logs', ['id' => $b->id, 'status' => 'pending']);
    }

    public function test_retry_without_stored_body_is_rejected(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $provider = EmailProvider::factory()->create(['is_default' => true]);

        $log = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'status' => 'failed',
            'driver' => $provider->driver->value,
            'meta' => ['source' => 'admin'],
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/email-logs/'.$log->id.'/retry')
            ->assertStatus(422);
    }
}
