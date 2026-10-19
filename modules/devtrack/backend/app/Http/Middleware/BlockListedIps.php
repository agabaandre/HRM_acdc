<?php

namespace App\Http\Middleware;

use App\Services\BlockedIpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests from IPs that admins have blocked (e.g. from audit logs).
 */
class BlockListedIps
{
    public function __construct(
        private readonly BlockedIpService $blockedIps,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Always allow health probes.
        if ($path === 'api/v1/health' || $path === 'up') {
            return $next($request);
        }

        $ip = $request->ip();
        if ($this->blockedIps->isBlocked($ip)) {
            return response()->json([
                'message' => 'Access denied from this IP address.',
            ], 403);
        }

        return $next($request);
    }
}
