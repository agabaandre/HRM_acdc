<?php

namespace Modules\Share\Http\Middleware;

use App\Support\SsoJwt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\PortalUser;
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
        $expected = trim((string) config('share.api_token', ''));
        if ($expected === '' || $token === '') {
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

        if (! Schema::hasTable('user') || ! Schema::hasTable('staff')) {
            return false;
        }

        $user = PortalUser::query()
            ->where('status', 1)
            ->whereHas('staff', fn ($q) => $q->where('work_email', $email))
            ->first();

        if (! $user && Schema::hasColumn('user', 'email')) {
            $user = PortalUser::query()->where('status', 1)->where('email', $email)->first();
        }

        // CI auth_mdl::login often matched on staff.work_email via join — also try raw email column variants.
        if (! $user) {
            $row = DB::table('user as u')
                ->join('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
                ->where('u.status', 1)
                ->where('s.work_email', $email)
                ->select('u.user_id')
                ->first();
            if ($row) {
                $user = PortalUser::query()->find($row->user_id);
            }
        }

        if (! $user || ! $user->password) {
            return false;
        }

        return password_verify($password, $user->password);
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
