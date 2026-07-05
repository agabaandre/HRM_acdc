<?php

namespace App\Http\Controllers;

use App\Support\AppBasePath;
use App\Support\StaffSsoCodeStore;
use App\Support\StaffSsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SsoAcceptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $jwt = trim((string) ($_POST['staff_sso_jwt'] ?? $request->input('staff_sso_jwt', '')));
        if ($jwt === '') {
            $code = trim((string) ($_POST['sso_code'] ?? $request->input('sso_code', '')));
            if ($code !== '') {
                $record = StaffSsoCodeStore::consume($code, 'finance_management');
                $jwt = (string) ($record['jwt'] ?? '');
            }
        }
        if ($jwt === '') {
            return $this->loginRedirect();
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
        } catch (\Throwable) {
            return $this->loginRedirect();
        }
    }

    private function loginRedirect(): RedirectResponse
    {
        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

        return redirect($base.'/auth');
    }
}
