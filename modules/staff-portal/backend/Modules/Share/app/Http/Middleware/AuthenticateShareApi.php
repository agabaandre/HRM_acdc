<?php

namespace Modules\Share\Http\Middleware;

use App\Support\SsoJwt;
use Closure;
use Illuminate\Http\Request;
use Modules\Share\Services\ShareAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Staff Share API (APM / Helpdesk):
 * - HTTP Basic (portal user email + password) — CI3 parity
 * - Authorization: Bearer &lt;JWT&gt; (Share token or SSO JWT)
 * - Authorization: Bearer &lt;STAFF_API_TOKEN&gt; or URL path token segment
 */
class AuthenticateShareApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authenticate($request)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'error' => 'Authentication Failed! Invalid Request',
        ], 401, ['WWW-Authenticate' => 'Basic realm="Staff Share API", Bearer']);
    }

    protected function authenticate(Request $request): bool
    {
        $pathToken = (string) $request->route('token', '');
        if ($pathToken !== '' && $this->staticTokenValid($pathToken)) {
            return true;
        }

        $bearer = $this->bearerToken($request);
        if ($bearer !== null) {
            if ($this->staticTokenValid($bearer)) {
                return true;
            }
            if ($this->jwtValid($bearer)) {
                return true;
            }
        }

        return $this->basicAuthValid($request);
    }

    protected function staticTokenValid(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $expected = trim((string) config('share.api_token', ''));
        // Empty cached config (e.g. blank STAFF_API_TOKEN= at config:cache time) —
        // fall back to resolved root/.env token or the Share default.
        if ($expected === '' && class_exists(\Staff\Shared\StaffApiCredentials::class)) {
            $resolved = \Staff\Shared\StaffApiCredentials::resolve();
            $expected = trim((string) ($resolved['token'] !== ''
                ? $resolved['token']
                : \Staff\Shared\StaffApiCredentials::DEFAULT_TOKEN));
        }
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    protected function jwtValid(string $token): bool
    {
        $payload = SsoJwt::decode($token);
        if (! is_array($payload)) {
            return false;
        }

        $aud = (string) ($payload['aud'] ?? '');
        $expectedAud = (string) config('share.jwt_audience', 'share-api');
        if ($aud === $expectedAud) {
            return true;
        }

        // Staff SSO JWTs (CBP hand-off) — require staff_id
        return isset($payload['staff_id']) && (int) $payload['staff_id'] > 0;
    }

    protected function basicAuthValid(Request $request): bool
    {
        $email = $request->getUser();
        $password = $request->getPassword();
        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return false;
        }

        return app(ShareAuthService::class)->credentialsValid($email, $password) !== null;
    }

    protected function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
