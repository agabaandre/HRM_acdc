<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Staff\Shared\MailConfigCrypto;
use Staff\Shared\StaffPortalMailClient;
use Tests\TestCase;

class StaffPortalMailClientTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = 'base64:'.base64_encode(str_repeat('k', 32));
        config([
            'app.mail_config_key' => $this->key,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
    }

    public function test_portal_success_path(): void
    {
        Http::fake([
            'http://portal.test/share/mail/send' => Http::response(['success' => true, 'driver' => 'http'], 200),
        ]);

        $client = new StaffPortalMailClient(
            baseUrl: 'http://portal.test',
            token: 'test-token',
            dispatch: 'portal',
            configKey: $this->key,
        );

        $client->send('user@example.org', 'Subj', '<p>hi</p>');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://portal.test/share/mail/send'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['subject'] === 'Subj';
        });
    }

    public function test_auto_falls_back_to_local_http_after_portal_503(): void
    {
        $crypto = new MailConfigCrypto($this->key);
        $enc = $crypto->encrypt([
            'driver' => 'http',
            'config' => [
                'base_url' => 'https://notifications.test/api/v1',
                'client_id' => 'cid',
                'client_secret' => 'sec',
            ],
            'from_address' => 'n@example.org',
            'from_name' => 'Notify',
        ]);

        Http::fake([
            'http://portal.test/share/mail/send' => Http::response(['success' => false, 'error' => 'down'], 503),
            'http://portal.test/share/mail/active-config' => Http::response([
                'success' => true,
                'data' => [
                    'driver' => 'http',
                    'from_address' => 'n@example.org',
                    'from_name' => 'Notify',
                    'expires_at' => $enc['expires_at'],
                    'ciphertext' => $enc['ciphertext'],
                    'iv' => $enc['iv'],
                    'tag' => $enc['tag'],
                ],
            ], 200),
            'https://notifications.test/api/v1/integrations/auth/token' => Http::response([
                'access_token' => 'jwt-token',
            ], 200),
            'https://notifications.test/api/v1/integrations/send' => Http::response(['ok' => true], 200),
        ]);

        $client = new StaffPortalMailClient(
            baseUrl: 'http://portal.test',
            token: 'test-token',
            dispatch: 'auto',
            configKey: $this->key,
        );

        $client->send(['user@example.org'], 'Fallback', '<p>ok</p>');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/share/mail/send'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/share/mail/active-config'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/integrations/send'));
    }

    public function test_local_sender_callback_used_when_provided(): void
    {
        $called = false;
        Http::fake([
            'http://portal.test/share/mail/send' => Http::response(['success' => false], 503),
        ]);

        $client = new StaffPortalMailClient(
            baseUrl: 'http://portal.test',
            token: 't',
            dispatch: 'auto',
            configKey: $this->key,
            localSender: function ($to, $subject, $html) use (&$called): void {
                $called = true;
                $this->assertSame('a@b.c', $to);
                $this->assertSame('S', $subject);
                $this->assertSame('<p>x</p>', $html);
            },
        );

        $client->send('a@b.c', 'S', '<p>x</p>');
        $this->assertTrue($called);
    }

    public function test_local_http_fallback_includes_attachments(): void
    {
        $crypto = new MailConfigCrypto($this->key);
        $enc = $crypto->encrypt([
            'driver' => 'http',
            'config' => [
                'base_url' => 'https://notifications.test/api/v1',
                'client_id' => 'cid',
                'client_secret' => 'sec',
            ],
            'from_address' => 'n@example.org',
            'from_name' => 'Notify',
        ]);

        Http::fake([
            'http://portal.test/share/mail/send' => Http::response(['success' => false, 'error' => 'down'], 503),
            'http://portal.test/share/mail/active-config' => Http::response([
                'success' => true,
                'data' => [
                    'driver' => 'http',
                    'from_address' => 'n@example.org',
                    'from_name' => 'Notify',
                    'expires_at' => $enc['expires_at'],
                    'ciphertext' => $enc['ciphertext'],
                    'iv' => $enc['iv'],
                    'tag' => $enc['tag'],
                ],
            ], 200),
            'https://notifications.test/api/v1/integrations/auth/token' => Http::response([
                'token' => 'jwt-token',
            ], 200),
            'https://notifications.test/api/v1/integrations/send' => Http::response(['ok' => true], 200),
        ]);

        $client = new StaffPortalMailClient(
            baseUrl: 'http://portal.test',
            token: 'test-token',
            dispatch: 'auto',
            configKey: $this->key,
        );

        $client->send('user@example.org', 'With file', '<p>ok</p>', [
            'attachments' => [
                [
                    'name' => 'note.txt',
                    'content' => 'payload-bytes',
                    'content_type' => 'text/plain',
                ],
            ],
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/integrations/send')) {
                return false;
            }
            $data = $request->data();

            return ($data['attachments'][0]['filename'] ?? null) === 'note.txt'
                && ($data['attachments'][0]['content'] ?? null) === base64_encode('payload-bytes')
                && ($data['attachments'][0]['content_type'] ?? null) === 'text/plain';
        });
    }
}
