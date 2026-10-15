<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Settings\Services\PortalLanguageService;
use Modules\Settings\Support\PortalLocalesConfig;

/**
 * Locale catalog / apply for Risk Register SSO Bearer sessions.
 * Settings module routes use auth:sanctum; RR uses AuthenticateRiskSession.
 */
class RiskLocaleController extends Controller
{
    public function __construct(
        protected PortalLanguageService $languages,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $userLocale = null;
        if ($staffId > 0) {
            try {
                $user = PortalUser::query()->where('auth_staff_id', $staffId)->where('status', 1)->first();
                if ($user instanceof PortalUser) {
                    $userLocale = (string) ($user->langauge ?? '');
                }
            } catch (\Throwable) {
                $userLocale = null;
            }
        }
        $cookie = (string) $request->cookie((string) PortalLocalesConfig::get('cookie', 'staff_portal_locale'), '');

        try {
            $data = $this->languages->catalog($userLocale ?: null, $cookie !== '' ? $cookie : null);
        } catch (\Throwable) {
            $data = [
                'locale' => 'en',
                'direction' => 'ltr',
                'is_rtl' => false,
                'languages' => [
                    ['code' => 'en', 'name' => 'English', 'flag' => '', 'google_code' => 'en', 'is_rtl' => false],
                ],
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:32'],
        ]);

        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $user = $staffId > 0
            ? PortalUser::query()->where('auth_staff_id', $staffId)->where('status', 1)->first()
            : null;

        if ($user instanceof PortalUser) {
            $payload = $this->languages->applyLocale($user, (string) $validated['locale']);
        } else {
            // Still return the catalog for the requested locale (cookie-only).
            $payload = $this->languages->catalog((string) $validated['locale'], (string) $validated['locale']);
            $payload['locale'] = (string) $validated['locale'];
        }

        $cookieName = (string) PortalLocalesConfig::get('cookie', 'staff_portal_locale');
        $minutes = (int) PortalLocalesConfig::get('cookie_minutes', 525600);

        return response()->json(['data' => $payload])
            ->cookie($cookieName, $payload['locale'], $minutes, '/', null, false, false, false, 'Lax');
    }
}
