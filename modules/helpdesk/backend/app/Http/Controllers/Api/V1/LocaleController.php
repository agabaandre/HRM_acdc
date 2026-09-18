<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Locale catalog / apply for Helpdesk SPA (Sanctum).
 * Cookie staff_portal_locale is shared with Staff Portal / APM / Risk Register.
 */
class LocaleController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $cookie = (string) $request->cookie(PortalLocale::cookieName(), '');
        $preferred = $cookie !== '' ? $cookie : (string) $request->query('locale', '');

        return response()->json([
            'data' => PortalLocale::catalog($preferred !== '' ? $preferred : null),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:32'],
        ]);

        $locale = PortalLocale::apply((string) $validated['locale']);
        $catalog = PortalLocale::catalog($locale);

        return response()->json(['data' => $catalog])
            ->cookie(
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
