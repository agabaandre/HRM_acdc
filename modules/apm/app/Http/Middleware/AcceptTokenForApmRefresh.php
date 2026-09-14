<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For POST /api/apm/v1/auth/refresh: copy token from JSON/form/query into Authorization
 * when the client did not send a Bearer header (common for mobile refresh calls).
 */
class AcceptTokenForApmRefresh
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            return $next($request);
        }

        foreach (['access_token', 'token'] as $key) {
            $t = $request->input($key);
            if (is_string($t) && trim($t) !== '') {
                $request->headers->set('Authorization', 'Bearer '.trim($t));

                return $next($request);
            }
        }

        $q = $request->query('token');
        if (is_string($q) && trim($q) !== '') {
            $request->headers->set('Authorization', 'Bearer '.trim($q));
        }

        return $next($request);
    }
}
