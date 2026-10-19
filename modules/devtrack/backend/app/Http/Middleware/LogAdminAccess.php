<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminAccess
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null && $this->shouldLog($request)) {
            try {
                $this->audit->logRouteAccess($request);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        // Avoid recursive noise when browsing the audit log itself on GET,
        // but still record mutations and non-list GETs via event_type.
        // Skip high-frequency polling of dashboard/me if desired — keep me quiet.
        if ($request->isMethod('GET') && $request->is('api/v1/admin/auth/me')) {
            return false;
        }

        return true;
    }
}
