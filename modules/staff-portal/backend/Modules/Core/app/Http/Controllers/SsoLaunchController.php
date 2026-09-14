<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\StaffSsoLaunch;

/**
 * CI3-compatible SSO launch + session refresh endpoints under /staff/home and /staff/auth.
 */
class SsoLaunchController extends Controller
{
    /**
     * SPA / API: issue accept URL + JWT (Bearer Sanctum).
     */
    public function apiLaunch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $moduleKey = trim((string) $request->input('module_key', ''));
        $result = StaffSsoLaunch::prepareLaunch($user, $moduleKey);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Launch failed'], (int) ($result['status'] ?? 400));
        }

        if (! empty($result['redirect_url'])) {
            return response()->json([
                'redirect_url' => $result['redirect_url'],
                'label' => $result['label'] ?? '',
                'module_key' => $result['module_key'] ?? $moduleKey,
            ]);
        }

        return response()->json([
            'accept_url' => $result['accept_url'],
            'staff_sso_jwt' => $result['staff_sso_jwt'],
            'label' => $result['label'] ?? '',
            'module_key' => $result['module_key'] ?? $moduleKey,
        ]);
    }

    /**
     * SPA / API: mint a fresh SSO JWT from the Sanctum user.
     */
    public function apiRefreshSso(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $session = StaffSsoLaunch::compactClaims($user->toSessionArray());
        $token = SsoJwt::encode($session, (int) config('staff-portal.sso.token_ttl', 7200));

        return response()->json([
            'ok' => true,
            'sso_token' => $token,
            'expires_in' => (int) config('staff-portal.sso.token_ttl', 7200),
        ]);
    }

    /**
     * Legacy: CI3 auth/refreshCSRF — cbp-sso-launch.js needs a non-empty token.
     */
    public function refreshCsrf(): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
            'csrf_token_name' => 'africacdc_csrf_token',
        ]);
    }

    /**
     * Legacy: CI3 auth/refresh_sso_session — used by cbp-session-refresh.js.
     */
    public function refreshSsoSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $session = StaffSsoLaunch::compactClaims($user->toSessionArray());
        $token = SsoJwt::encode($session, (int) config('staff-portal.sso.token_ttl', 7200));

        return response()->json([
            'ok' => true,
            'sso_token' => $token,
            'expires_in' => (int) config('staff-portal.sso.token_ttl', 7200),
        ]);
    }

    /**
     * Legacy: CI3 home/launch_module — auto-POST JWT to module accept URL.
     */
    public function launchModule(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            $spa = rtrim((string) config('staff-portal.spa_url', '/staff/'), '/');

            return redirect()->away($spa.'/login');
        }

        $moduleKey = trim((string) $request->input('module_key', ''));
        $result = StaffSsoLaunch::prepareLaunch($user, $moduleKey);
        if (! ($result['ok'] ?? false)) {
            abort((int) ($result['status'] ?? 400), (string) ($result['message'] ?? 'Launch failed'));
        }

        if (! empty($result['redirect_url'])) {
            $url = (string) $result['redirect_url'];
            if (! preg_match('#^https?://#i', $url)) {
                $base = rtrim((string) config('staff-portal.spa_url', '/staff/'), '/');
                $url = $base.'/'.ltrim($url, '/');
            }

            return redirect()->away($url);
        }

        $acceptUrl = htmlspecialchars((string) $result['accept_url'], ENT_QUOTES, 'UTF-8');
        $jwt = htmlspecialchars((string) $result['staff_sso_jwt'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string) ($result['label'] ?? 'module'), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>Opening {$label}…</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f4f6f8; color: #2c3e50; }
    .box { text-align: center; padding: 2rem; }
  </style>
</head>
<body>
  <div class="box">
    <p>Opening <strong>{$label}</strong>…</p>
    <p style="font-size:0.9rem;color:#6c757d;">Please wait.</p>
  </div>
  <form id="cbp-sso-form" method="post" action="{$acceptUrl}">
    <input type="hidden" name="staff_sso_jwt" value="{$jwt}">
  </form>
  <script>document.getElementById('cbp-sso-form').submit();</script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
