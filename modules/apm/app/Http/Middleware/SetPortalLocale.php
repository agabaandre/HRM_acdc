<?php

namespace App\Http\Middleware;

use App\Support\PortalLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPortalLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $hadCookie = (string) $request->cookie(PortalLocale::cookieName(), '') !== ''
            || (string) ($_COOKIE[PortalLocale::cookieName()] ?? '') !== '';

        $locale = PortalLocale::resolveFromRequest();
        PortalLocale::apply($locale, false);

        /** @var Response $response */
        $response = $next($request);

        // Seed shared cookie from profile default so other CBP modules pick it up.
        if (! $hadCookie && $locale !== '') {
            $response->headers->setCookie(cookie(
                PortalLocale::cookieName(),
                $locale,
                PortalLocale::cookieMinutes(),
                '/',
                null,
                false,
                false,
                false,
                'Lax'
            ));
        }

        return $response;
    }
}
