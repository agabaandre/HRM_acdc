<?php

namespace App\Http\Controllers;

use App\Support\StaffSsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

        return redirect($base.'/auth/logout');
    }
}
