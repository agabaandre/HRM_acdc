<?php

namespace App\Http\Controllers;

use App\Support\AppBasePath;
use App\Support\StaffSsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Secure SSO: POST staff_sso_jwt from Staff portal launch (JWT never in URL).
     */
    public function ssoAccept(Request $request): RedirectResponse
    {
        $jwt = trim((string) ($_POST['staff_sso_jwt'] ?? $request->input('staff_sso_jwt', '')));
        if ($jwt === '') {
            $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

            return redirect($base.'/login');
        }

        try {
            $json = StaffSsoToken::decode($jwt);
            if (! $json) {
                throw new \RuntimeException('Invalid token format');
            }

            session([
                'user' => $json,
                'base_url' => $json['base_url'] ?? '',
                'permissions' => $json['permissions'] ?? [],
                'last_activity' => now(),
            ]);
            session()->save();

            return redirect()->to(AppBasePath::url('/dashboard'));
        } catch (\Throwable $e) {
            Log::error('Finance SSO accept failed: '.$e->getMessage());
            $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

            return redirect($base.'/login');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

        return redirect($base.'/auth/logout');
    }
}
