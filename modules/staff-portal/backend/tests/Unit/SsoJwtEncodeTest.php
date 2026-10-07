<?php

namespace Tests\Unit;

use App\Support\SsoJwt;
use PHPUnit\Framework\TestCase;

class SsoJwtEncodeTest extends TestCase
{
    public function test_encode_tolerates_invalid_utf8_in_claims(): void
    {
        $token = SsoJwt::encode([
            'staff_id' => 1,
            'name' => "Bad\xB1bytes",
            'email' => 'user@example.com',
        ], 60);

        $this->assertNotSame('', $token);
        $this->assertStringContainsString('.', $token);
    }
}
