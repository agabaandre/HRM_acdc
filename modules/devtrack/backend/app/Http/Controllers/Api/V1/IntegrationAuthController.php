<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IntegrationTokenRequest;
use App\Models\ExternalIntegration;
use App\Services\AuditLogService;
use App\Services\IntegrationJwtService;
use Illuminate\Http\JsonResponse;

class IntegrationAuthController extends Controller
{
    public function token(
        IntegrationTokenRequest $request,
        IntegrationJwtService $jwt,
        AuditLogService $audit,
    ): JsonResponse {
        $clientId = (string) $request->validated('client_id');
        $integration = ExternalIntegration::query()
            ->where('slug', $clientId)
            ->where('is_active', true)
            ->first();

        if ($integration === null || ! $integration->verifyClientSecret((string) $request->validated('client_secret'))) {
            $audit->log('Client authentication failed', [
                'actor_type' => AuditLogService::ACTOR_CLIENT,
                'event_type' => 'client_auth_failed',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'user_name' => $clientId,
                'attempted_email' => $clientId,
                'target_table' => 'external_integrations',
                'new_values' => ['client_id' => $clientId, 'reason' => 'invalid_credentials'],
            ]);

            return response()->json(['message' => 'Invalid client credentials.'], 401);
        }

        if (! $integration->allowsIp($request->ip())) {
            $audit->log('Client authentication blocked by IP allowlist', [
                'actor_type' => AuditLogService::ACTOR_CLIENT,
                'event_type' => 'client_auth_ip_denied',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'integration' => $integration,
                'user_name' => $integration->name,
                'attempted_email' => $integration->slug,
                'target_table' => 'external_integrations',
                'target_id' => $integration->id,
                'new_values' => [
                    'client_id' => $integration->slug,
                    'ip' => $request->ip(),
                    'reason' => 'ip_not_allowed',
                ],
            ]);

            return response()->json(['message' => 'IP address not allowed for this integration.'], 403);
        }

        $token = $jwt->issue($integration);

        $audit->log('Client authenticated', [
            'actor_type' => AuditLogService::ACTOR_CLIENT,
            'event_type' => 'client_auth_success',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'integration' => $integration,
            'user_name' => $integration->name,
            'attempted_email' => $integration->slug,
            'target_table' => 'external_integrations',
            'target_id' => $integration->id,
            'new_values' => ['client_id' => $integration->slug],
        ]);

        $integration->update(['last_used_at' => now()]);

        return response()->json([
            ...$token,
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'slug' => $integration->slug,
            ],
        ]);
    }
}
