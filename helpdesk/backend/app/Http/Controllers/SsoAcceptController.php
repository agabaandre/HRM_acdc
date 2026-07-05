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

            $spaPath = trim((string) env('HELPDESK_SPA_PATH', 'staff/helpdesk'), '/');
            $redirect = '/'.$spaPath.'/';

            return response()->view('sso-bridge', [
                'token' => (string) $data['token'],
                'redirect' => $redirect,
            ], 200)->withHeaders([
                'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]);
        } catch (Throwable $e) {
            Log::warning('Helpdesk SSO accept failed', [
                'error' => $e->getMessage(),
                'jwt_len' => strlen($jwt),
            ]);

            return $this->redirectStaffHome('unauthorized');
        }
    }

    private function redirectStaffHome(string $reason): RedirectResponse
    {
        $host = request()->getHost();
        $scheme = request()->getScheme();
        if ($host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))) {
            return redirect($scheme.'://'.$host.'/staff/home/index?helpdesk_error=sso&helpdesk_error_reason='.urlencode($reason));
        }

        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

        return redirect()->away($base.'/home/index?helpdesk_error=sso&helpdesk_error_reason='.urlencode($reason));
    }
}
