<?php

namespace Tests\Unit;

use App\Services\HttpNotificationsMailClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpNotificationsMailClientAttachmentsTest extends TestCase
{
    public function test_normalizes_portal_binary_attachments_to_api_shape(): void
    {
        $client = new HttpNotificationsMailClient;
        $raw = '%PDF-1.4 fake';
        $out = $client->normalizeAttachments([
            [
                'name' => 'invoice.pdf',
                'content' => $raw,
                'content_type' => 'application/pdf',
            ],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('invoice.pdf', $out[0]['filename']);
        $this->assertSame('application/pdf', $out[0]['content_type']);
        $this->assertSame(base64_encode($raw), $out[0]['content']);
    }

    public function test_send_includes_attachments_in_json_payload(): void
    {
        config([
            'mail.http.base_url' => 'https://notifications.test/api/v1',
            'mail.http.client_id' => 'cid',
            'mail.http.client_secret' => 'sec',
            'cache.default' => 'array',
        ]);

        Http::fake([
            'https://notifications.test/api/v1/integrations/auth/token' => Http::response([
                'token' => 'jwt-token',
                'expires_in' => 3600,
            ], 200),
            'https://notifications.test/api/v1/integrations/send' => Http::response(['ok' => true], 200),
        ]);

        $client = new HttpNotificationsMailClient;
        $client->send(
            'user@example.com',
            'Welcome',
            '<p>Hello</p>',
            [],
            [],
            [
                [
                    'name' => 'note.txt',
                    'content' => 'hello attach',
                    'content_type' => 'text/plain',
                ],
            ],
        );

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/integrations/send')) {
                return false;
            }
            $data = $request->data();

            return ($data['to'] ?? null) === 'user@example.com'
                && isset($data['attachments'][0]['filename'], $data['attachments'][0]['content'], $data['attachments'][0]['content_type'])
                && $data['attachments'][0]['filename'] === 'note.txt'
                && $data['attachments'][0]['content'] === base64_encode('hello attach')
                && $data['attachments'][0]['content_type'] === 'text/plain';
        });
    }
}
