<?php

namespace Tests\Feature;

use App\Enums\EmailDriver;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Services\EmailAttachmentService;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailAttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_can_queue_email_with_base64_attachment(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);
        Storage::fake('local');

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'Staff Portal',
            'slug' => 'staff-portal',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $jwt = $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => $integration->slug,
            'client_secret' => $clientSecret,
        ])->json('token');

        Queue::fake();

        $content = 'Hello attachment';
        $response = $this->withToken($jwt)->postJson('/api/v1/integrations/send', [
            'to' => 'user@example.com',
            'subject' => 'With attachment',
            'body' => '<p>See attached</p>',
            'attachments' => [
                [
                    'filename' => 'note.txt',
                    'content' => base64_encode($content),
                    'content_type' => 'text/plain',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('attachment_count', 1);

        Queue::assertPushed(SendEmailJob::class);

        $log = EmailLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->meta['attachment_count'] ?? null);
        $this->assertCount(1, $log->meta['attachments'] ?? []);
        $path = $log->meta['attachments'][0]['path'] ?? null;
        $this->assertIsString($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($content, Storage::disk('local')->get($path));
    }

    public function test_rejects_blocked_executable_attachment(): void
    {
        config(['integration.jwt_secret' => 'testing-jwt-secret-key-with-at-least-sixty-four-characters-long!!']);

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log',
            'driver' => EmailDriver::Log,
            'config' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $clientSecret = 'IntegrationSecret2026!';
        $integration = ExternalIntegration::query()->create([
            'name' => 'Staff Portal',
            'slug' => 'staff-portal-exe',
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'email_provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $jwt = $this->postJson('/api/v1/integrations/auth/token', [
            'client_id' => $integration->slug,
            'client_secret' => $clientSecret,
        ])->json('token');

        $this->withToken($jwt)->postJson('/api/v1/integrations/send', [
            'to' => 'user@example.com',
            'subject' => 'Bad file',
            'body' => 'nope',
            'attachments' => [
                [
                    'filename' => 'malware.exe',
                    'content' => base64_encode('MZ'),
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['attachments.0.filename']);
    }

    public function test_delivery_loads_attachment_and_cleans_up_after_send(): void
    {
        Storage::fake('local');
        Queue::fake();

        $provider = EmailProvider::query()->create([
            'name' => 'Log',
            'slug' => 'log-deliver',
            'driver' => EmailDriver::Log,
            'config' => [],
            'from_address' => 'noreply@example.com',
            'from_name' => 'Email Server',
            'is_default' => true,
            'is_active' => true,
        ]);

        $payload = app(EmailAttachmentService::class)->normalizeFromRequest([
            [
                'filename' => 'report.csv',
                'content' => base64_encode("a,b\n1,2\n"),
                'content_type' => 'text/csv',
            ],
        ]);

        $log = app(EmailDispatchService::class)->queue(
            to: 'user@example.com',
            subject: 'CSV',
            body: '<p>Attached</p>',
            isHtml: true,
            providerId: $provider->id,
            attachmentPayload: $payload,
        );

        $path = $log->meta['attachments'][0]['path'];
        Storage::disk('local')->assertExists($path);

        $delivered = app(EmailDispatchService::class)->deliver($log->id);

        $this->assertSame('sent', $delivered->status);
        $this->assertTrue($delivered->meta['attachments_delivered'] ?? false);
        $this->assertArrayNotHasKey('attachments', $delivered->meta ?? []);
        Storage::disk('local')->assertMissing($path);
    }
}
