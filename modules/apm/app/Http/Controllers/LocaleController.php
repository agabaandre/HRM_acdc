<?php

namespace App\Http\Controllers;

use App\Support\PortalLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class LocaleController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $locale = PortalLocale::normalize(
            (string) $request->query('locale', PortalLocale::resolveFromRequest())
        );

        return response()->json([
            'data' => PortalLocale::catalog($locale),
        ]);
    }

    public function apply(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:32'],
        ]);

        $locale = PortalLocale::apply((string) $validated['locale'], true);
        $catalog = PortalLocale::catalog($locale);
        $cookie = $this->sharedLocaleCookie($locale);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $catalog])->withCookie($cookie);
        }

        return redirect()->back()->withCookie($cookie);
    }

    private function sharedLocaleCookie(string $locale): Cookie
    {
        return cookie(
            PortalLocale::cookieName(),
            $locale,
            PortalLocale::cookieMinutes(),
            '/',
            null,
            false,
            false,
            false,
            'Lax'
        );
    }
}
