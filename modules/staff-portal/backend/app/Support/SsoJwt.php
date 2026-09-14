<?php

namespace App\Support;

/**
 * HS256 JWT compatible with CodeIgniter Home::build_sso_jwt and APM token decode.
 */
final class SsoJwt
{
    public static function encode(array $payload, ?int $ttlSeconds = 7200): string
    {
        $secret = self::secret();
        if ($secret === '') {
            return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $now = time();
        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['exp'] = $payload['exp'] ?? ($now + $ttlSeconds);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $p = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $h.'.'.$p, $secret, true);

        return $h.'.'.$p.'.'.self::base64UrlEncode($sig);
    }

    public static function decode(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $parts = explode('.', $token);
        $secret = self::secret();

        if (count($parts) === 3 && $secret !== '') {
            [$h, $p, $s] = $parts;
            $expected = self::base64UrlEncode(hash_hmac('sha256', $h.'.'.$p, $secret, true));
            if (! hash_equals($expected, $s)) {
                return null;
            }
            $json = self::base64UrlDecode($p);
            $payload = is_string($json) ? json_decode($json, true) : null;
            if (! is_array($payload)) {
                return null;
            }
            $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
            if ($exp > 0 && $exp < time()) {
                return null;
            }

            return $payload;
        }

        $decoded = base64_decode($token, true);
        $json = is_string($decoded) ? json_decode($decoded, true) : null;

        return is_array($json) ? $json : null;
    }

    public static function secret(): string
    {
        $secret = env('JWT_SECRET', env('APP_KEY', ''));

        return is_string($secret) ? trim($secret) : '';
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
