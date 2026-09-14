<?php

namespace App\Services;

use InvalidArgumentException;

class StaffPortalJwtService
{
    /**
     * Decode and verify HS256 JWT from the CodeIgniter staff portal (same format as Finance / APM links).
     *
     * @return array<string, mixed>
     */
    public function decodeVerified(string $jwt, string $secret): array
    {
        $secret = trim($secret);
        if ($secret === '') {
            throw new InvalidArgumentException('JWT secret is empty.');
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT structure.');
        }

        [$h, $p, $s] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $h.'.'.$p, $secret, true));
        if (! hash_equals($expected, $s)) {
            throw new InvalidArgumentException('Invalid JWT signature.');
        }

        $payload = json_decode($this->base64UrlDecode($p), true);
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid JWT payload.');
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new InvalidArgumentException('JWT has expired.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $input): string
    {
        $input = strtr($input, '-_', '+/');
        $pad = strlen($input) % 4;
        if ($pad > 0) {
            $input .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($input, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url segment.');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
