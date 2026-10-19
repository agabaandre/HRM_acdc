<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\StaffSsoController;
use App\Http\Requests\Api\V1\StaffSsoRequest;
use App\Services\HelpdeskPermissionService;
use App\Services\StaffPortalJwtService;
use App\Support\StaffSsoCodeStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class SsoAcceptController extends Controller
{
    public function __invoke(Request $request): SymfonyResponse
    {
        $jwt = trim((string) ($_POST['staff_sso_jwt'] ?? $request->input('staff_sso_jwt', '')));
        if ($jwt === '') {
            $code = trim((string) ($_POST['sso_code'] ?? $request->input('sso_code', '')));
            if ($code !== '') {
                $record = StaffSsoCodeStore::consume($code, 'helpdesk_itsm');
                $jwt = (string) ($record['jwt'] ?? '');
            }
        }
        if ($jwt === '') {
            return $this->redirectStaffHome('invalid');
        }

        try {
            $apiRequest = StaffSsoRequest::create('/api/v1/auth/staff-sso', 'POST', [
                'token' => $jwt,
            ]);
            $apiRequest->headers->set('Accept', 'application/json');
            $apiRequest->setContainer(app());
            $apiRequest->validateResolved();

            $response = app(StaffSsoController::class)(
                $apiRequest,
                app(StaffPortalJwtService::class),
                app(HelpdeskPermissionService::class),
            );
            if ($response->getStatusCode() === 403) {
                return $this->redirectStaffHome('forbidden');
            }
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Staff SSO API returned HTTP '.$response->getStatusCode());
            }
            $data = json_decode($response->getContent(), true);
            if (! is_array($data) || empty($data['token'])) {
                throw new \RuntimeException('Missing API token in SSO response');
            }

            $next = $this->safeModuleNext($request);
            $redirect = '/'.$this->spaPath().'/'.($next !== '' ? $next : '');

            return response()->view('sso-bridge', [
                'token' => (string) $data['token'],
                'redirect' => $redirect,
            ], 200)->withHeaders([
                'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]);
        } catch (Throwable $e) {
            try {
                Log::warning('Helpdesk SSO accept failed', [
                    'error' => $e->getMessage(),
                    'jwt_len' => strlen($jwt),
                ]);
            } catch (Throwable) {
                // Never turn a logging/storage failure into HTTP 500 for SSO.
            }

            return $this->redirectStaffHome('unauthorized');
        }
    }

    private function redirectStaffHome(string $reason): RedirectResponse
    {
        $configured = rtrim((string) config('staff-portal.spa_url', env('HELPDESK_STAFF_PORTAL_URL', '')), '/');
        $path = parse_url($configured, PHP_URL_PATH);
        $home = is_string($path) && $path !== '' && $path !== '/' ? rtrim($path, '/') : '';

        return redirect($home.'/?helpdesk_error=sso&helpdesk_error_reason='.urlencode($reason));
    }

    /**
     * Public mount of the Helpdesk SPA, from HELPDESK_FRONTEND_URL so a nested
     * install is not sent to /staff/helpdesk.
     */
    private function spaPath(): string
    {
        $path = parse_url(rtrim((string) config('helpdesk.frontend_url', ''), '/'), PHP_URL_PATH);
        if (is_string($path) && $path !== '' && $path !== '/') {
            return trim($path, '/');
        }

        $fromEnv = trim((string) env('HELPDESK_SPA_PATH', 'staff/helpdesk'), '/');

        return $fromEnv !== '' ? $fromEnv : 'staff/helpdesk';
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
