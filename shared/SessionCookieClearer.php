<?php

namespace Staff\Shared;

use Symfony\Component\HttpFoundation\Response;

/**
 * Clear Laravel session cookies without Symfony TypeErrors on logout.
 *
 * Symfony HttpFoundation 6.2+ / 7 clearCookie signature is:
 *   clearCookie(name, path, domain, secure: bool, httpOnly: bool, sameSite: ?string)
 *
 * Common breakages this helper avoids:
 * - config('session.secure') is null → Argument #4 ($secure) must be bool
 * - passing the removed $raw bool into $sameSite → Argument #6 must be ?string
 */
final class SessionCookieClearer
{
    /**
     * @param  list<string>  $extraPaths  Additional cookie paths (e.g. "/apm", "/staff/apm")
     */
    public static function forgetSessionCookies(Response $response, array $extraPaths = []): void
    {
        $name = (string) (config('session.cookie') ?: 'laravel_session');
        $path = (string) (config('session.path') ?: '/');
        $domain = config('session.domain');
        $domain = is_string($domain) && $domain !== '' ? $domain : null;
        $secure = (bool) config('session.secure', false);
        $sameSite = config('session.same_site');
        $sameSite = is_string($sameSite) && $sameSite !== '' ? $sameSite : null;

        $paths = [$path, ...$extraPaths];
        $paths = array_values(array_unique(array_filter(
            array_map(static fn ($p) => is_string($p) ? $p : '', $paths),
            static fn (string $p) => $p !== '',
        )));

        foreach ($paths as $cookiePath) {
            self::clear($response, $name, $cookiePath, $domain, $secure, $sameSite);
            if ($domain !== null) {
                self::clear($response, $name, $cookiePath, null, $secure, $sameSite);
            }
        }
    }

    public static function clear(
        Response $response,
        string $name,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        ?string $sameSite = null,
        bool $httpOnly = true,
    ): void {
        $response->headers->clearCookie(
            $name,
            $path,
            $domain,
            $secure,
            $httpOnly,
            $sameSite,
        );
    }
}
