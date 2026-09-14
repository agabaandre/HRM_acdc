<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApmApiUserContext
{
    /**
     * Set request attribute so user_session() returns API user's staff context.
     * Does not touch web session - API auth is independent.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();
        if ($user && method_exists($user, 'toSessionArray')) {
            $request->attributes->set('api_user_session', $user->toSessionArray());
            $request->attributes->set('api_user', $user);
            if (method_exists($user, 'update') && in_array('last_used_at', $user->getFillable() ?? [])) {
                $user->timestamps = false;
                $user->update(['last_used_at' => now()]);
                $user->timestamps = true;
            }
        }

        return $next($request);
    }
}
