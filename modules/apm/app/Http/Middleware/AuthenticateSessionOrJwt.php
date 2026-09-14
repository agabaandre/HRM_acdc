<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow access with either web session or JWT (Bearer token).
 * Use for routes that should work in-browser (session) or via API clients (JWT).
 *
 * Pass JWT via:
 * - Header: Authorization: Bearer <token>
 * - Query (GET only): ?token=<token> (use with AcceptTokenInQuery so it becomes Bearer)
 */
class AuthenticateSessionOrJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('user')) {
            return $next($request);
        }

        $token = $request->bearerToken();
        if ($token !== null && $token !== '') {
            try {
                $user = auth('api')->user();
                if ($user && method_exists($user, 'toSessionArray')) {
                    session(['user' => $user->toSessionArray()]);
                    return $next($request);
                }
            } catch (\Throwable $e) {
                // Invalid or expired token; fall through to redirect
            }
        }

        $loginUrl = rtrim(env('BASE_URL', 'http://localhost/staff'), '/') . '/auth';
        return redirect($loginUrl);
    }
}
