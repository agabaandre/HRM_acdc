<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && trim((string) $request->query('token')) !== '') {
            return $next($request);
        }

        $user = session('user', []);
        if (! is_array($user) || empty($user['staff_id'])) {
            $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

            return redirect($base.'/auth/login');
        }

        $permissionId = (string) config('finance.sso_permission_id', 92);
        $permissions = array_map('strval', (array) session('permissions', []));
        if (! in_array($permissionId, $permissions, true)) {
            abort(403, 'You do not have access to Finance.');
        }

        session(['last_activity' => now()]);

        return $next($request);
    }
}
