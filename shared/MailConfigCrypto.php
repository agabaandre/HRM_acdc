<?php

namespace Staff\Shared;

use RuntimeException;

/**
 * AES-256-GCM helpers for Share mail active-config payloads.
 */
final class MailConfigCrypto
{
    public function __construct(
        private readonly ?string $keyMaterial = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ciphertext: string, iv: string, tag: string, expires_at: string}
     */
    public function encrypt(array $payload, int $ttlSeconds = 3600): array
    {
        $key = $this->keyBytes();
        $iv = random_bytes(12);
        $tag = '';
        $plain = json_encode($payload, JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Mail config encrypt failed');
        }

        return [
            'ciphertext' => base64_encode($cipher),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'expires_at' => gmdate('c', time() + $ttlSeconds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decrypt(string $ciphertext, string $iv, string $tag): array
    {
        $plain = openssl_decrypt(
            base64_decode($ciphertext, true) ?: '',
            'aes-256-gcm',
            $this->keyBytes(),
            OPENSSL_RAW_DATA,
            base64_decode($iv, true) ?: '',
            base64_decode($tag, true) ?: ''
        );
        if ($plain === false) {
            throw new RuntimeException('Mail config decrypt failed');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function keyBytes(): string
    {
        $raw = (string) ($this->keyMaterial ?? '');
        if ($raw === '') {
            $raw = (string) (getenv('STAFF_MAIL_CONFIG_KEY') ?: '');
        }
        if ($raw === '') {
            $raw = (string) (getenv('APP_KEY') ?: '');
        }
        if ($raw === '' && function_exists('config')) {
            $raw = (string) (config('app.mail_config_key') ?: config('app.key') ?: '');
        }

        if ($raw === '') {
            throw new RuntimeException('STAFF_MAIL_CONFIG_KEY or APP_KEY is required to encrypt mail config');
        }

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            $raw = $decoded !== false ? $decoded : substr($raw, 7);
        }

        return hash('sha256', $raw, true);
    }
}
