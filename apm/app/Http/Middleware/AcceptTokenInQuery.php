<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For GET requests, copy ?token= to Authorization: Bearer so the user can open
 * attachment URLs in the browser (e.g. .../attachments/activity/408/0?token=eyJ...).
 * Only applies when no Authorization header is already set.
 */
class AcceptTokenInQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && !$request->bearerToken() && $request->filled('token')) {
            $token = $request->query('token');
            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
