<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Correlation-ID');
        $id = is_string($incoming) && preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('correlation_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
