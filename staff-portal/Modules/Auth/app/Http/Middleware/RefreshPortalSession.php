<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps session user payload (especially permissions) in sync with the database.
 */
class RefreshPortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof PortalUser) {
            $fresh = $user->toSessionArray();
            $existing = session('user', []);
            session(['user' => array_merge($existing, $fresh)]);
        }

        return $next($request);
    }
}
