<?php

namespace App\Support;

class StaffSsoToken
{
    /**
     * Decode Staff portal SSO token (JWT HS256 or legacy base64 JSON).
     *
     * @return array<string, mixed>|null
     */
    public static function decode(?string $token): ?array
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }
        $token = trim($token);
        $parts = explode('.', $token);
        $jwtSecret = (string) (env('JWT_SECRET', env('APP_KEY', '')));

        if (count($parts) === 3 && $jwtSecret !== '') {
            [$h, $p, $s] = $parts;
            $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $h.'.'.$p, $jwtSecret, true)), '+/', '-_'), '=');
            if (hash_equals($sig, $s)) {
                $payloadJson = base64_decode(strtr($p, '-_', '+/').str_repeat('=', (4 - strlen($p) % 4) % 4));
                $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;
                if (is_array($payload)) {
                    $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
                    if ($exp === 0 || $exp >= time()) {
                        return $payload;
                    }
                }
            }
        }

        $decoded = base64_decode($token, true);
        $json = is_string($decoded) ? json_decode($decoded, true) : null;

        return is_array($json) ? $json : null;
    }
}
