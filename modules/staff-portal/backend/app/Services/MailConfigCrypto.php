<?php

namespace App\Services;

use Staff\Shared\MailConfigCrypto as SharedMailConfigCrypto;

/**
 * Laravel-bound wrapper around shared AES-GCM mail config crypto.
 */
final class MailConfigCrypto
{
    private SharedMailConfigCrypto $inner;

    public function __construct()
    {
        $this->inner = new SharedMailConfigCrypto(
            (string) (config('app.mail_config_key') ?: config('app.key') ?: '')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ciphertext: string, iv: string, tag: string, expires_at: string}
     */
    public function encrypt(array $payload, int $ttlSeconds = 3600): array
    {
        return $this->inner->encrypt($payload, $ttlSeconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function decrypt(string $ciphertext, string $iv, string $tag): array
    {
        return $this->inner->decrypt($ciphertext, $iv, $tag);
    }
}
