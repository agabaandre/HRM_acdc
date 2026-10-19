<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created in admin must enroll an authenticator before using the panel.
 */
class EnsureTotpSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->mustSetupTotp()) {
            return $next($request);
        }

        $path = $request->path();
        $allowed = [
            'api/v1/admin/auth/me',
            'api/v1/admin/auth/logout',
            'api/v1/admin/auth/2fa/status',
            'api/v1/admin/auth/2fa/totp/setup',
            'api/v1/admin/auth/2fa/totp/confirm',
        ];

        foreach ($allowed as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You must set up an authenticator app before continuing.',
            'must_setup_totp' => true,
        ], 403);
    }
}
