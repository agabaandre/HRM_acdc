<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Staff\Shared\StaffShareHttp;
use Tests\TestCase;

class StaffShareHttpTest extends TestCase
{
    #[Test]
    public function issues_jwt_then_fetches_staff_with_bearer(): void
    {
        Http::fake([
            'https://share.test/staff/backend/share/token' => Http::response([
                'success' => true,
                'access_token' => 'jwt-abc',
                'expires_in' => 3600,
            ], 200),
            'https://share.test/staff/backend/share/get_current_staff*' => Http::response([
                ['staff_id' => 1, 'fname' => 'Ada', 'lname' => 'Lovelace'],
            ], 200),
        ]);

        $client = new StaffShareHttp(
            'https://share.test/staff/backend',
            'ada@example.org',
            'secret',
            'static-token',
        );

        $rows = $client->getJson('/share/get_current_staff', ['limit' => 1]);
        $this->assertSame(1, $rows[0]['staff_id'] ?? null);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://share.test/staff/backend/share/token'
                && $request->hasHeader('Authorization');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/share/get_current_staff')
                && ! str_contains($request->url(), '/static-token')
                && $request->hasHeader('Authorization', 'Bearer jwt-abc');
        });
    }

    #[Test]
    public function falls_back_to_static_path_token_when_credentials_rejected(): void
    {
        Http::fake([
            'https://share.test/staff/backend/share/token' => Http::response([
                'success' => false,
                'error' => 'Invalid credentials',
            ], 401),
            'https://share.test/staff/backend/share/get_current_staff/static-token*' => Http::response([
                ['staff_id' => 2, 'fname' => 'Grace', 'lname' => 'Hopper'],
            ], 200),
        ]);

        $client = new StaffShareHttp(
            'https://share.test/staff/backend',
            'bad@example.org',
            'wrong',
            'static-token',
        );

        $rows = $client->getJson('/share/get_current_staff', ['limit' => 1]);
        $this->assertSame(2, $rows[0]['staff_id'] ?? null);
    }

    #[Test]
    public function is_configured_with_token_only(): void
    {
        $client = new StaffShareHttp('https://share.test/staff/backend', '', '', 'static-token');
        $this->assertTrue($client->isConfigured());
    }
}
