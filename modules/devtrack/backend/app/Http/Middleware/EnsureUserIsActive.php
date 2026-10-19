<?php

namespace App\Http\Middleware;

use App\Services\BlockedEmailService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(
        private readonly BlockedEmailService $blockedEmails,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        if ($user !== null && $this->blockedEmails->isBlocked($user->email)) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'This email address has been blocked.'], 403);
        }

        return $next($request);
    }
}
