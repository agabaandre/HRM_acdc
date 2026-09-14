<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StatefulApiCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // PHPUnit skips CSRF while APP_ENV=testing. Force a non-testing env so
        // Sanctum's stateful API CSRF actually runs, matching production.
        $this->app['env'] = 'production';

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.env' => 'production',
            'app.url' => 'http://localhost',
            'sanctum.stateful' => ['cbp.africacdc.org'],
        ]);
        URL::forceRootUrl('http://localhost');
        URL::forceScheme('http');
    }

    public function test_leave_apply_post_from_spa_does_not_fail_csrf_when_bearer_token_is_sent(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://cbp.africacdc.org',
            'Referer' => 'https://cbp.africacdc.org/staff/staff-portal/leave/apply',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer test-token',
        ])->post('/api/v1/leave/requests', [
            'leave_id' => '1',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'requested_days' => '5',
            'email_leave' => 'a@b.c',
            'mobile_leave' => '123',
            'supporting_staff' => '2',
        ]);

        $this->assertNotSame(
            419,
            $response->status(),
            'Leave apply must not 419 CSRF when the SPA sends a Sanctum bearer token. Response: '.$response->getContent()
        );
    }
}
