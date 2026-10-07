<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffApiCredentialsProbeTest extends TestCase
{
    public function test_probe_succeeds_via_static_token_when_jwt_rejected(): void
    {
        Http::fake([
            '*/share/login' => Http::response(['success' => false, 'error' => 'Invalid credentials'], 401),
            '*/share/token' => Http::response(['success' => false, 'error' => 'Invalid credentials'], 401),
            '*/share/get_current_staff*' => Http::response([['SAPNO' => '1'], ['SAPNO' => '2']], 200),
            '*/share/divisions*' => Http::response([['id' => 1], ['id' => 2], ['id' => 3]], 200),
        ]);

        $result = \Staff\Shared\StaffApiCredentials::probe(null, [
            'base_url' => 'http://staff-api.test/staff/backend',
            'username' => 'user@example.com',
            'password' => 'wrong',
            'token' => \Staff\Shared\StaffApiCredentials::DEFAULT_TOKEN,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('CONNECTED', $result['status']);
        $this->assertStringContainsString('static STAFF_API_TOKEN', $result['mode']);
    }

    public function test_probe_failure_message_when_credentials_set_but_unreachable(): void
    {
        Http::fake([
            '*/share/*' => Http::response(['success' => false, 'error' => 'Authentication Failed! Invalid Request'], 401),
        ]);

        // Force no in-process Share service by probing with a non-local token mismatch path:
        // Use a wrong static token so in-process fallback (if present) also fails token check.
        $result = \Staff\Shared\StaffApiCredentials::probe(null, [
            'base_url' => 'http://staff-api.test/staff/backend',
            'username' => 'user@example.com',
            'password' => 'secret',
            'token' => 'definitely-not-the-share-token',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credentials are set but Share API did not accept them', $result['message']);
        $this->assertStringNotContainsString('FAILED — set STAFF_API_USERNAME', $result['message']);
    }
}
