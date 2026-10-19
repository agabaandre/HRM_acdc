<?php

namespace App\Http\Middleware;

use App\Services\IntegrationJwtService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationJwt
{
    public function __construct(
        private readonly IntegrationJwtService $jwt,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Bearer JWT required.'], 401);
        }

        try {
            $integration = $this->jwt->integrationFromToken($token);
        } catch (AuthenticationException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        if (! $integration->allowsIp($request->ip())) {
            return response()->json(['message' => 'IP address not allowed for this integration.'], 403);
        }

        $request->attributes->set('integration', $integration);

        return $next($request);
    }
}
