<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class ApiDocsAuthCookie
{
    public const NAME = 'email_server_docs_token';

    public static function attach(JsonResponse $response, string $plainTextToken): JsonResponse
    {
        return $response->withCookie(self::make($plainTextToken));
    }

    public static function clear(JsonResponse $response): JsonResponse
    {
        return $response->withCookie(self::forget());
    }

    public static function make(string $plainTextToken): Cookie
    {
        return cookie(
            self::NAME,
            $plainTextToken,
            60 * 24,
            '/',
            null,
            self::secure(),
            true,
            false,
            'Lax',
        );
    }

    public static function forget(): Cookie
    {
        return cookie(
            self::NAME,
            '',
            -2628000,
            '/',
            null,
            self::secure(),
            true,
            false,
            'Lax',
        );
    }

    private static function secure(): bool
    {
        return (bool) config('session.secure', false)
            || str_starts_with((string) config('app.url'), 'https://');
    }
}
