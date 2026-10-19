<?php

namespace App\Http\Controllers;

use App\Enums\UserApprovalStatus;
use App\Enums\UserRegistrationSource;
use App\Models\User;
use App\Support\SsoJwt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Opens DevTrack from the CBP session. No second login. */
class SsoAcceptController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $jwt = trim((string) $request->input('staff_sso_jwt', ''));
        $payload = $jwt !== '' ? SsoJwt::decode($jwt) : null;
        $email = strtolower(trim((string) ($payload['work_email'] ?? $payload['email'] ?? '')));
        $staffId = (int) ($payload['staff_id'] ?? 0);

        if (! is_array($payload) || $staffId < 1 || $email === '') {
            return redirect('/'.$this->spaPath().'/login');
        }

        $user = User::query()->where('staff_id', $staffId)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = trim(((string) ($payload['fname'] ?? '')).' '.((string) ($payload['lname'] ?? '')));
        }
        if ($name === '') {
            $name = $email;
        }

        $isAdmin = (int) ($payload['role_id'] ?? $payload['role'] ?? 0) === 10;
        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(40),
                'staff_id' => $staffId,
                'is_admin' => $isAdmin,
                'is_active' => true,
                'approval_status' => UserApprovalStatus::Approved,
                'approved_at' => now(),
                'registration_source' => UserRegistrationSource::StaffSso,
                'totp_required' => false,
                'two_factor_email_enabled' => false,
                'two_factor_totp_enabled' => false,
            ]);
        } else {
            $user->forceFill([
                'staff_id' => $staffId,
                'is_active' => true,
                'approval_status' => UserApprovalStatus::Approved,
                'is_admin' => $user->is_admin || $isAdmin,
                'totp_required' => false,
            ])->save();
        }

        $token = $user->createToken('cbp-sso')->plainTextToken;
        $next = ltrim(trim((string) $request->input('next', '')), '/');
        if ($next !== '' && (str_contains($next, '..') || str_contains($next, '://'))) {
            $next = '';
        }
        $redirect = '/'.$this->spaPath().'/'.$next;

        return response()->view('sso-bridge', [
            'token' => $token,
            'redirect' => $redirect,
        ], 200)->withHeaders([
            'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function spaPath(): string
    {
        $path = parse_url(rtrim((string) config('app.url', ''), '/'), PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim($path, '/') : 'staff/devtrack';
    }
}
