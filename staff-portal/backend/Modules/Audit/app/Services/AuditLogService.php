<?php

namespace Modules\Audit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\UserLog;

/**
 * ISO-oriented audit logging aligned with CI3 user_logs + LogUserAccess hook.
 */
class AuditLogService
{
    public function log(string $action, array $context = []): void
    {
        if (! \App\Support\LegacySchema::has('user_logs')) {
            return;
        }

        $userId = Auth::id() ?? session('user.user_id') ?? null;
        $request = request();

        $data = [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $request->ip() ?: '0.0.0.0',
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];

        if ($this->auditColumnsActive()) {
            $method = strtoupper((string) ($context['http_method'] ?? $request->method()));
            $uri = (string) ($context['request_uri'] ?? $request->path());
            if (strlen($uri) > 500) {
                $uri = substr($uri, 0, 500).'…';
            }
            $data['http_method'] = $method;
            $data['request_uri'] = $uri;
            $data['event_type'] = $context['event_type'] ?? $this->inferEventType($method);

            foreach (['target_table', 'target_id'] as $key) {
                if (! empty($context[$key])) {
                    $data[$key] = $context[$key];
                }
            }

            if (! empty($context['new_values']) && is_array($context['new_values'])) {
                $data['new_values'] = $this->encodeJson($context['new_values']);
            }
            if (! empty($context['old_values']) && is_array($context['old_values'])) {
                $data['old_values'] = $this->encodeJson($context['old_values']);
            }
            if (! empty($context['mutation_payload']) && is_array($context['mutation_payload'])) {
                $merged = array_merge(
                    json_decode($data['new_values'] ?? '{}', true) ?: [],
                    ['_http_request' => $context['mutation_payload']]
                );
                $data['new_values'] = $this->encodeJson($merged);
            }
        }

        if (config('staff-portal.audit.integrity_chain', false)) {
            $this->insertWithIntegrityChain($data);
        } else {
            UserLog::query()->create($data);
        }
    }

    public function logRouteAccess(Request $request): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $context = [
            'http_method' => $request->method(),
            'request_uri' => $request->path(),
        ];
        if (str_contains($request->path(), 'audit/logs')) {
            $context['event_type'] = 'audit_repository';
        }
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $payload = $request->except(['password', 'password_confirmation', '_token']);
            if ($payload !== []) {
                $context['mutation_payload'] = $payload;
            }
        }
        $this->log("Accessed route: {$route}", $context);
    }

    public function logRecordChange(
        string $eventType,
        string $targetTable,
        string|int $targetId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->log("Record audit: {$targetTable} #{$targetId} — {$eventType}", [
            'event_type' => 'record_'.preg_replace('/[^a-z0-9_]+/i', '', $eventType),
            'target_table' => $targetTable,
            'target_id' => (string) $targetId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    protected function auditColumnsActive(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('user_logs', 'http_method');
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

    protected function encodeJson(array $values): string
    {
        $json = json_encode($values, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 60000) {
            $json = substr($json, 0, 60000).'…';
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function insertWithIntegrityChain(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS l', ['staff_user_logs_chain']);
            if (! $lock || (int) ($lock->l ?? 0) !== 1) {
                UserLog::query()->create($data);

                return;
            }
            $prev = DB::table('user_logs')->orderByDesc('id')->value('audit_row_hash');
            $data['audit_prev_hash'] = $prev ?: str_repeat('0', 64);
            $row = UserLog::query()->create($data);
            $canonical = json_encode([
                'id' => $row->id,
                'user_id' => $data['user_id'],
                'action' => $data['action'],
                'audit_prev_hash' => $data['audit_prev_hash'],
                'created_at' => $row->created_at,
            ], JSON_THROW_ON_ERROR);
            $hash = hash('sha256', $canonical);
            DB::table('user_logs')->where('id', $row->id)->update(['audit_row_hash' => $hash]);
            DB::select('SELECT RELEASE_LOCK(?)', ['staff_user_logs_chain']);
        });
    }
}
