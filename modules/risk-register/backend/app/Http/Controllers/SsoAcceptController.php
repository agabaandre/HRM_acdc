<?php

namespace App\Http\Controllers;

use App\Support\SsoJwt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            return response()->json(['message' => 'staff_sso_jwt is required.'], 422);
        }

        try {
            $payload = SsoJwt::decode($jwt);
            if (! is_array($payload) || empty($payload['staff_id'])) {
                return response()->json(['message' => 'Invalid SSO token.'], 401);
            }

            $apiToken = Str::random(64);
            $request->session()->put('risk_register', [
                'staff_id' => (int) $payload['staff_id'],
                'name' => (string) ($payload['name'] ?? ''),
                'email' => (string) ($payload['email'] ?? $payload['work_email'] ?? ''),
                'permissions' => $payload['permissions'] ?? [],
                'division_id' => (int) ($payload['division_id'] ?? 0),
                'role_id' => (int) ($payload['role_id'] ?? $payload['role'] ?? 0),
                'api_token' => $apiToken,
                'sso_claims' => $payload,
            ]);

            $spaPath = trim((string) env('RISK_REGISTER_SPA_PATH', 'staff/risk-register'), '/');
            $redirect = '/'.$spaPath.'/';

            return response()->view('sso-bridge', [
                'token' => $apiToken,
                'redirect' => $redirect,
            ], 200)->withHeaders([
                'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]);
        } catch (Throwable $e) {
            Log::warning('Risk Register SSO accept failed', ['error' => $e->getMessage()]);

            return $this->redirectStaffHome('unauthorized');
        }
    }

    private function redirectStaffHome(string $reason): RedirectResponse
    {
        $host = request()->getHost();
        $scheme = request()->getScheme();
        if ($host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))) {
            return redirect($scheme.'://'.$host.'/staff/?risk_error=sso&risk_error_reason='.urlencode($reason));
        }
        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

        return redirect()->away($base.'/?risk_error=sso&risk_error_reason='.urlencode($reason));
    }
}
