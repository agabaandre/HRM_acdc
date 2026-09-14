<?php

namespace Modules\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Audit\Services\AuditLogService;
use Symfony\Component\HttpFoundation\Response;

class LogStaffPortalAccess
{
    public function __construct(
        protected AuditLogService $audit
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() || session()->has('user')) {
            $this->audit->logRouteAccess($request);
        }

        return $response;
    }
}
