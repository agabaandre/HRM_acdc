<?php

namespace App\Http\Middleware;

use App\Support\ApiDocsAuthCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow Swagger UI (top-level browser navigation) to authenticate via
 * the httpOnly docs cookie set at admin login, while API clients still
 * use Authorization: Bearer.
 */
class PromoteApiDocsTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            $token = $request->cookie(ApiDocsAuthCookie::NAME);
            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
