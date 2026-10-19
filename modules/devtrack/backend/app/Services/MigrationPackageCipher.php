<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * AES-256-GCM encryption for migration packages.
 * Each export uses a fresh 256-bit random key (not a password) so offline
 * brute-force of the ciphertext is computationally infeasible (~2^256).
 */
class MigrationPackageCipher
{
    public const CIPHER = 'aes-256-gcm';

    public const KEY_BYTES = 32;

    public const NONCE_BYTES = 12;

    public const TAG_BYTES = 16;

    /**
     * @return array{key_raw: string, key_encoded: string}
     */
    public function generateKey(): array
    {
        $raw = random_bytes(self::KEY_BYTES);

        return [
            'key_raw' => $raw,
            'key_encoded' => $this->encodeKey($raw),
        ];
    }

    public function encodeKey(string $rawKey): string
    {
        if (strlen($rawKey) !== self::KEY_BYTES) {
            throw new InvalidArgumentException('Migration encryption key must be '.self::KEY_BYTES.' bytes.');
        }

        return rtrim(strtr(base64_encode($rawKey), '+/', '-_'), '=');
    }

    public function decodeKey(string $encoded): string
    {
        $encoded = trim($encoded);
        if ($encoded === '') {
            throw new InvalidArgumentException('Migration encryption key is required.');
        }

        $padded = strtr($encoded, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($padded, true);
        if ($raw === false || strlen($raw) !== self::KEY_BYTES) {
            throw new InvalidArgumentException(
                'Invalid migration encryption key. Paste the full key shown when the package was downloaded.'
            );
        }

        return $raw;
    }

    /**
     * @return array{nonce: string, tag: string, ciphertext: string}
     */
    public function encrypt(string $plaintext, string $rawKey): array
    {
        if (strlen($rawKey) !== self::KEY_BYTES) {
            throw new InvalidArgumentException('Migration encryption key must be '.self::KEY_BYTES.' bytes.');
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $rawKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Failed to encrypt migration package.');
        }

        return [
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    public function decrypt(string $ciphertextB64, string $nonceB64, string $tagB64, string $rawKey): string
    {
        if (strlen($rawKey) !== self::KEY_BYTES) {
            throw new InvalidArgumentException('Migration encryption key must be '.self::KEY_BYTES.' bytes.');
        }

        $ciphertext = base64_decode($ciphertextB64, true);
        $nonce = base64_decode($nonceB64, true);
        $tag = base64_decode($tagB64, true);

        if ($ciphertext === false || $nonce === false || $tag === false
            || strlen($nonce) !== self::NONCE_BYTES || strlen($tag) !== self::TAG_BYTES) {
            throw new InvalidArgumentException('Migration package ciphertext is corrupt or incomplete.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $rawKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            // Uniform error — do not distinguish wrong key vs tampering (avoid oracles).
            throw new InvalidArgumentException(
                'Could not decrypt migration package. Check the encryption key and file integrity.'
            );
        }

        return $plaintext;
    }
}
