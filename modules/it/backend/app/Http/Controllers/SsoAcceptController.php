<?php

namespace App\Http\Controllers;

use App\Support\SsoJwt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * CBP SSO accept for Risk Register (mirrors Helpdesk: POST staff_sso_jwt → SPA bridge).
 */
class SsoAcceptController extends Controller
{
    public function __invoke(Request $request): SymfonyResponse
    {
        $jwt = trim((string) $request->input('staff_sso_jwt', ''));
        if ($jwt === '') {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json(['message' => 'staff_sso_jwt is required.'], 422);
            }

            return $this->redirectAccessError('missing_token');
        }

        try {
            $payload = SsoJwt::decode($jwt);
            if (! is_array($payload) || empty($payload['staff_id'])) {
                if ($request->expectsJson() || $request->isJson()) {
                    return response()->json(['message' => 'Invalid SSO token.'], 401);
                }

                return $this->redirectAccessError('invalid_token');
            }

            $apiToken = Str::random(64);
            $sessionPayload = [
                'staff_id' => (int) $payload['staff_id'],
                'name' => (string) ($payload['name'] ?? ''),
                'email' => (string) ($payload['email'] ?? $payload['work_email'] ?? ''),
                'permissions' => $payload['permissions'] ?? [],
                'division_id' => (int) ($payload['division_id'] ?? 0),
                'role_id' => (int) ($payload['role_id'] ?? $payload['role'] ?? 0),
                'api_token' => $apiToken,
                'sso_claims' => $payload,
            ];
            $request->session()->put('risk_register', $sessionPayload);
            try {
                Cache::put('risk_api_token:'.$apiToken, $sessionPayload, now()->addHours(12));
            } catch (Throwable $e) {
                Log::warning('Finance SSO token cache put failed', ['error' => $e->getMessage()]);
            }

            $next = $this->safeModuleNext($request);
            if ($next === '') {
                $next = 'dashboard';
            }
            $redirect = '/'.$this->spaPath().'/'.$next;

            return response()->view('sso-bridge', [
                'token' => $apiToken,
                'redirect' => $redirect,
            ], 200)->withHeaders([
                'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]);
        } catch (Throwable $e) {
            try {
                Log::warning('Finance SSO accept failed', ['error' => $e->getMessage()]);
            } catch (Throwable) {
                // Never turn a logging/storage failure into HTTP 500 for SSO.
            }

            return $this->redirectAccessError('unauthorized');
        }
    }

    private function redirectAccessError(string $reason): RedirectResponse
    {
        return redirect('/'.$this->spaPath().'/access-error?reason='.rawurlencode($reason));
    }

    /**
     * Public mount of the Finance SPA, taken from APP_URL so a nested
     * install (for example /ACDC/2026/cbp/finance) is not sent to /staff/finance.
     */
    private function spaPath(): string
    {
        $path = parse_url(rtrim((string) config('app.url', ''), '/'), PHP_URL_PATH);
        if (is_string($path) && $path !== '' && $path !== '/') {
            return trim($path, '/');
        }

        $fromEnv = trim((string) env('FINANCE_SPA_PATH', env('RISK_REGISTER_SPA_PATH', 'staff/finance')), '/');

        return $fromEnv !== '' ? $fromEnv : 'staff/finance';
    }

    /** Relative path posted with SSO. Stays inside this module. */
    private function safeModuleNext(Request $request): string
    {
        $next = ltrim(trim((string) $request->input('next', '')), '/');
        if ($next === '' || str_contains($next, '..') || str_contains($next, '\\') || str_contains($next, '://')) {
            return '';
        }
        if (! preg_match('#\A[A-Za-z0-9_./?=&%-]+\z#', $next)) {
            return '';
        }

        return $next;
    }
}
