<?php

namespace App\Support;

/** HS256 JWT issued by the CBP staff portal when a module is opened. */
final class SsoJwt
{
    public static function decode(string $token): ?array
    {
        $token = trim($token);
        $parts = explode('.', $token);
        $secret = self::secret();
        if (count($parts) !== 3 || $secret === '') {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $header.'.'.$payload, $secret, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $json = self::base64UrlDecode($payload);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return null;
        }

        $exp = isset($data['exp']) ? (int) $data['exp'] : 0;
        if ($exp > 0 && $exp < time()) {
            return null;
        }

        return $data;
    }

    public static function secret(): string
    {
        // Prefer an explicit CBP secret, then the shared staff-portal JWT_SECRET
        // (same chain IT / Helpdesk / Staff Portal use). APP_KEY is last resort.
        foreach ([
            config('app.cbp_staff_jwt_secret'),
            env('CBP_STAFF_JWT_SECRET', ''),
            env('JWT_SECRET', ''),
            env('APP_KEY', ''),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
