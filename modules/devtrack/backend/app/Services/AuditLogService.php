<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-portal-style audit logging for admin panel + integration client auth.
 */
class AuditLogService
{
    public const ACTOR_SYSTEM_USER = 'system_user';

    public const ACTOR_CLIENT = 'client';

    public const ACTOR_SYSTEM = 'system';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'challenge_token',
        'secret',
        'client_secret',
        'recovery_codes',
        'code',
        'totp_secret',
    ];

    public function __construct(
        private readonly SuspiciousAuditDetector $suspicious,
    ) {}

    public function log(string $action, array $context = []): void
    {
        $request = request();
        $user = Auth::user();
        if ($user === null && isset($context['user']) && $context['user'] instanceof User) {
            $user = $context['user'];
        }

        $integration = null;
        if (isset($context['integration']) && $context['integration'] instanceof ExternalIntegration) {
            $integration = $context['integration'];
        }

        $method = strtoupper((string) ($context['http_method'] ?? $request?->method() ?? 'GET'));
        $uri = (string) ($context['request_uri'] ?? $request?->path() ?? '');
        if (strlen($uri) > 500) {
            $uri = substr($uri, 0, 500).'…';
        }

        $actorType = $this->resolveActorType($context, $user, $integration);

        $userEmail = $user?->email;
        if (isset($context['attempted_email']) && is_string($context['attempted_email']) && $context['attempted_email'] !== '') {
            $userEmail = $context['attempted_email'];
        } elseif ($integration !== null && $userEmail === null) {
            $userEmail = $integration->slug;
        }

        $userName = $user?->name ?? ($context['user_name'] ?? null);
        if ($userName === null && $integration !== null) {
            $userName = $integration->name;
        }

        $newValues = isset($context['new_values']) ? $this->sanitize($context['new_values']) : null;
        $ip = $request?->ip();

        $incoming = [
            'event_type' => $context['event_type'] ?? $this->inferEventType($method),
            'action' => $action,
            'ip_address' => $ip,
            'user_email' => $userEmail,
            'user_id' => $user?->id,
            'actor_type' => $actorType,
            'target_table' => $context['target_table'] ?? null,
            'http_method' => $method,
            'new_values' => $newValues,
        ];

        $flags = $this->suspicious->evaluate($incoming);

        $payload = [
            'user_id' => $user?->id,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'action' => $action,
            'event_type' => $incoming['event_type'],
            'http_method' => $method,
            'request_uri' => $uri,
            'target_table' => $context['target_table'] ?? null,
            'target_id' => isset($context['target_id']) ? (string) $context['target_id'] : null,
            'old_values' => isset($context['old_values']) ? $this->sanitize($context['old_values']) : null,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => substr((string) ($request?->userAgent() ?? ''), 0, 500),
        ];

        if (Schema::hasColumn('audit_logs', 'actor_type')) {
            $payload['actor_type'] = $actorType;
        }

        if (Schema::hasColumn('audit_logs', 'external_integration_id')) {
            $payload['external_integration_id'] = $integration?->id
                ?? (isset($context['external_integration_id']) ? (int) $context['external_integration_id'] : null);
        }

        if (Schema::hasColumn('audit_logs', 'is_suspicious')) {
            $payload['is_suspicious'] = $flags['is_suspicious'];
            $payload['suspicious_reasons'] = $flags['reasons'] !== []
                ? implode('; ', $flags['reasons'])
                : null;
        }

        AuditLog::query()->create($payload);

        if ($flags['is_suspicious'] && in_array($incoming['event_type'], [
            'auth_failed',
            'auth_2fa_failed',
            'auth_failed_inactive',
            'auth_login',
            'client_auth_failed',
            'client_auth_ip_denied',
            'client_auth_success',
        ], true)) {
            $this->suspicious->escalateRelatedAuthFailures(
                $ip,
                is_string($userEmail) ? strtolower($userEmail) : null,
                $flags['reasons'],
            );
        }
    }

    public function logRouteAccess(Request $request): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $context = [
            'actor_type' => self::ACTOR_SYSTEM_USER,
            'http_method' => $request->method(),
            'request_uri' => $request->path(),
        ];

        if (str_contains($request->path(), 'audit-logs')) {
            $context['event_type'] = 'audit_repository';
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $payload = $request->except(self::SENSITIVE_KEYS);
            if ($payload !== []) {
                $context['new_values'] = ['_http_request' => $payload];
                $context['event_type'] = $context['event_type'] ?? $this->inferEventType($request->method());
            }
        }

        $this->log("Accessed route: {$route}", $context);
    }

    public function logRecordChange(
        string $eventType,
        string $targetTable,
        string|int $targetId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $action = null,
    ): void {
        $this->log($action ?? "Record audit: {$targetTable} #{$targetId} — {$eventType}", [
            'actor_type' => self::ACTOR_SYSTEM_USER,
            'event_type' => 'record_'.preg_replace('/[^a-z0-9_]+/i', '', $eventType),
            'target_table' => $targetTable,
            'target_id' => (string) $targetId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveActorType(array $context, ?User $user, ?ExternalIntegration $integration): string
    {
        if (isset($context['actor_type']) && is_string($context['actor_type']) && $context['actor_type'] !== '') {
            return $context['actor_type'];
        }

        if ($integration !== null) {
            return self::ACTOR_CLIENT;
        }

        if ($user !== null || isset($context['attempted_email'])) {
            return self::ACTOR_SYSTEM_USER;
        }

        $eventType = (string) ($context['event_type'] ?? '');
        if (str_starts_with($eventType, 'client_')) {
            return self::ACTOR_CLIENT;
        }
        if (str_starts_with($eventType, 'auth_')) {
            return self::ACTOR_SYSTEM_USER;
        }

        return self::ACTOR_SYSTEM;
    }

    protected function inferEventType(string $method): string
    {
        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'access',
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function sanitize(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $keyStr = (string) $key;
            if (in_array(strtolower($keyStr), self::SENSITIVE_KEYS, true)
                || str_contains(strtolower($keyStr), 'password')
                || str_contains(strtolower($keyStr), 'secret')
            ) {
                $out[$keyStr] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                if (strtolower($keyStr) === 'attachments') {
                    $out[$keyStr] = array_map(function ($item) {
                        if (! is_array($item)) {
                            return '[attachment]';
                        }

                        return [
                            'filename' => $item['filename'] ?? $item['name'] ?? null,
                            'content_type' => $item['content_type'] ?? $item['mime_type'] ?? null,
                            'size' => $item['size'] ?? null,
                            'content' => '[redacted]',
                        ];
                    }, $value);

                    continue;
                }

                $out[$keyStr] = $this->sanitize($value);
                continue;
            }

            $out[$keyStr] = $value;
        }

        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        if (is_string($json) && strlen($json) > 60000) {
            return ['_truncated' => true, 'preview' => substr($json, 0, 2000)];
        }

        return $out;
    }
}
