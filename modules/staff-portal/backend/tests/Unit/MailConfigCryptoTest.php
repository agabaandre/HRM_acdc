<?php

namespace Tests\Unit;

use App\Services\MailConfigCrypto;
use Tests\TestCase;

class MailConfigCryptoTest extends TestCase
{
    public function test_round_trip(): void
    {
        config([
            'app.mail_config_key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
        ]);

        $crypto = app(MailConfigCrypto::class);
        $payload = [
            'driver' => 'http',
            'config' => ['client_secret' => 's'],
            'from_address' => 'a@b.c',
            'from_name' => 'N',
        ];
        $enc = $crypto->encrypt($payload);
        $this->assertArrayHasKey('ciphertext', $enc);
        $this->assertArrayHasKey('iv', $enc);
        $this->assertArrayHasKey('tag', $enc);
        $this->assertArrayHasKey('expires_at', $enc);

        $dec = $crypto->decrypt($enc['ciphertext'], $enc['iv'], $enc['tag']);
        $this->assertSame($payload, $dec);
    }
}
