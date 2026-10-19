<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResolveSuspiciousAuditLogRequest;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Support\SimpleExcelWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    private const EXPORT_MAX_ROWS = 10000;

    public function filterOptions(): JsonResponse
    {
        $eventTypes = AuditLog::query()
            ->whereNotNull('event_type')
            ->where('event_type', '!=', '')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->values()
            ->all();

        $targetTables = AuditLog::query()
            ->whereNotNull('target_table')
            ->where('target_table', '!=', '')
            ->distinct()
            ->orderBy('target_table')
            ->pluck('target_table')
            ->values()
            ->all();

        $methods = AuditLog::query()
            ->whereNotNull('http_method')
            ->where('http_method', '!=', '')
            ->distinct()
            ->orderBy('http_method')
            ->pluck('http_method')
            ->values()
            ->all();

        $actorTypes = Schema::hasColumn('audit_logs', 'actor_type')
            ? AuditLog::query()
                ->whereNotNull('actor_type')
                ->where('actor_type', '!=', '')
                ->distinct()
                ->orderBy('actor_type')
                ->pluck('actor_type')
                ->values()
                ->all()
            : [AuditLogService::ACTOR_SYSTEM_USER, AuditLogService::ACTOR_CLIENT];

        if ($actorTypes === []) {
            $actorTypes = [AuditLogService::ACTOR_SYSTEM_USER, AuditLogService::ACTOR_CLIENT];
        }

        return response()->json([
            'data' => [
                'event_types' => $eventTypes,
                'target_tables' => $targetTables,
                'http_methods' => $methods !== [] ? $methods : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'actor_types' => $actorTypes,
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $today = now()->toDateString();
        $hasSuspicious = Schema::hasColumn('audit_logs', 'is_suspicious');
        $hasResolution = Schema::hasColumn('audit_logs', 'suspicious_resolved_at');
        $hasActorType = Schema::hasColumn('audit_logs', 'actor_type');

        $totalEvents = AuditLog::query()->count();
        $eventsToday = AuditLog::query()->whereDate('created_at', $today)->count();

        $suspiciousTotal = 0;
        $suspiciousOpen = 0;
        $suspiciousResolved = 0;
        $suspiciousToday = 0;
        $suspiciousOpenToday = 0;
        $authFailedToday = 0;
        $clientEventsToday = 0;
        $systemUserEventsToday = 0;

        if ($hasSuspicious) {
            $suspiciousTotal = AuditLog::query()->where('is_suspicious', true)->count();
            $suspiciousToday = AuditLog::query()
                ->where('is_suspicious', true)
                ->whereDate('created_at', $today)
                ->count();

            if ($hasResolution) {
                $suspiciousOpen = AuditLog::query()->unresolvedSuspicious()->count();
                $suspiciousResolved = AuditLog::query()->resolvedSuspicious()->count();
                $suspiciousOpenToday = AuditLog::query()
                    ->unresolvedSuspicious()
                    ->whereDate('created_at', $today)
                    ->count();
            } else {
                $suspiciousOpen = $suspiciousTotal;
                $suspiciousOpenToday = $suspiciousToday;
            }
        }

        $authFailedToday = AuditLog::query()
            ->whereDate('created_at', $today)
            ->whereIn('event_type', ['auth_failed', 'auth_2fa_failed', 'auth_failed_inactive', 'client_auth_failed', 'client_auth_ip_denied'])
            ->count();

        if ($hasActorType) {
            $clientEventsToday = AuditLog::query()
                ->whereDate('created_at', $today)
                ->where('actor_type', AuditLogService::ACTOR_CLIENT)
                ->count();
            $systemUserEventsToday = AuditLog::query()
                ->whereDate('created_at', $today)
                ->where('actor_type', AuditLogService::ACTOR_SYSTEM_USER)
                ->count();
        }

        $cards = [
            [
                'key' => 'total_events',
                'title' => 'All events',
                'value' => $totalEvents,
                'subtitle' => 'All time',
                'icon' => 'mdi-clipboard-text-outline',
                'color' => 'primary',
                'filters' => [],
            ],
            [
                'key' => 'events_today',
                'title' => 'Events today',
                'value' => $eventsToday,
                'subtitle' => 'Recorded today',
                'icon' => 'mdi-calendar-today',
                'color' => 'info',
                'filters' => ['date_from' => $today, 'date_to' => $today],
            ],
            [
                'key' => 'suspicious_total',
                'title' => 'Suspicious (all time)',
                'value' => $suspiciousTotal,
                'subtitle' => 'Open + resolved',
                'icon' => 'mdi-shield-alert-outline',
                'color' => 'error',
                'filters' => ['suspicious' => '1'],
            ],
            [
                'key' => 'suspicious_today',
                'title' => 'Suspicious today',
                'value' => $suspiciousToday,
                'subtitle' => 'Flagged today',
                'icon' => 'mdi-calendar-alert',
                'color' => 'warning',
                'filters' => [
                    'suspicious' => '1',
                    'date_from' => $today,
                    'date_to' => $today,
                ],
            ],
            [
                'key' => 'suspicious_open',
                'title' => 'Open suspicious',
                'value' => $suspiciousOpen,
                'subtitle' => 'Needs review (all time)',
                'icon' => 'mdi-alert-circle-outline',
                'color' => 'error',
                'filters' => ['suspicious' => '1', 'resolved' => '0'],
            ],
            [
                'key' => 'suspicious_open_today',
                'title' => 'Open today',
                'value' => $suspiciousOpenToday,
                'subtitle' => 'Unresolved from today',
                'icon' => 'mdi-alert',
                'color' => 'warning',
                'filters' => [
                    'suspicious' => '1',
                    'resolved' => '0',
                    'date_from' => $today,
                    'date_to' => $today,
                ],
            ],
            [
                'key' => 'suspicious_resolved',
                'title' => 'Resolved',
                'value' => $suspiciousResolved,
                'subtitle' => 'Closed suspicious events',
                'icon' => 'mdi-check-circle-outline',
                'color' => 'success',
                'filters' => ['suspicious' => '1', 'resolved' => '1'],
            ],
            [
                'key' => 'auth_failed_today',
                'title' => 'Auth failures today',
                'value' => $authFailedToday,
                'subtitle' => 'Login / client auth',
                'icon' => 'mdi-lock-alert-outline',
                'color' => 'error',
                'filters' => [
                    'date_from' => $today,
                    'date_to' => $today,
                    'search' => 'auth_',
                ],
            ],
            [
                'key' => 'client_events_today',
                'title' => 'Client today',
                'value' => $clientEventsToday,
                'subtitle' => 'Integration actors',
                'icon' => 'mdi-api',
                'color' => 'secondary',
                'filters' => [
                    'actor_type' => AuditLogService::ACTOR_CLIENT,
                    'date_from' => $today,
                    'date_to' => $today,
                ],
            ],
            [
                'key' => 'system_user_events_today',
                'title' => 'System users today',
                'value' => $systemUserEventsToday,
                'subtitle' => 'Admin panel actors',
                'icon' => 'mdi-account-outline',
                'color' => 'primary',
                'filters' => [
                    'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                    'date_from' => $today,
                    'date_to' => $today,
                ],
            ],
        ];

        return response()->json([
            'data' => [
                'today' => $today,
                'totals' => [
                    'total_events' => $totalEvents,
                    'events_today' => $eventsToday,
                    'suspicious_total' => $suspiciousTotal,
                    'suspicious_open' => $suspiciousOpen,
                    'suspicious_resolved' => $suspiciousResolved,
                    'suspicious_today' => $suspiciousToday,
                    'suspicious_open_today' => $suspiciousOpenToday,
                    'auth_failed_today' => $authFailedToday,
                    'client_events_today' => $clientEventsToday,
                    'system_user_events_today' => $systemUserEventsToday,
                ],
                'cards' => $cards,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(10, (int) $request->integer('per_page', 50)));

        $paginator = $this->filteredQuery($request)
            ->with(['resolver:id,name,email'])
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AuditLog $log) => $this->transform($log))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'extended' => true,
            ],
        ]);
    }

    public function export(Request $request, AuditLogService $audit): Response
    {
        $query = $this->filteredQuery($request);
        $total = (clone $query)->count();
        $limit = min(self::EXPORT_MAX_ROWS, max(1, (int) $request->integer('limit', self::EXPORT_MAX_ROWS)));

        $logs = $query->with(['resolver:id,name,email'])->limit($limit)->get();

        $headers = [
            'ID',
            'When',
            'Category',
            'Suspicious',
            'Resolution',
            'Suspicious reasons',
            'Resolved at',
            'Resolved by',
            'Resolution note',
            'Actor name',
            'Actor email',
            'Client',
            'IP address',
            'Method',
            'Event',
            'Action',
            'URI',
            'Target table',
            'Target ID',
            'User agent',
            'Old values',
            'New values',
        ];

        $rows = $logs->map(function (AuditLog $log) {
            $actorType = $log->actor_type ?? AuditLogService::ACTOR_SYSTEM_USER;
            $actorLabel = match ($actorType) {
                AuditLogService::ACTOR_CLIENT => 'Client',
                AuditLogService::ACTOR_SYSTEM => 'System',
                default => 'System user',
            };

            $resolution = '—';
            if ($log->is_suspicious) {
                $resolution = $log->suspicious_resolved_at ? 'Resolved' : 'Open';
            }

            return [
                $log->id,
                $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                $actorLabel,
                (bool) ($log->is_suspicious ?? false) ? 'Yes' : 'No',
                $resolution,
                (string) ($log->suspicious_reasons ?? ''),
                $log->suspicious_resolved_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                (string) ($log->resolver?->email ?? ''),
                (string) ($log->suspicious_resolution_note ?? ''),
                (string) ($log->user_name ?? ''),
                (string) ($log->user_email ?? ''),
                (string) ($log->externalIntegration?->name ?? $log->externalIntegration?->slug ?? ''),
                (string) ($log->ip_address ?? ''),
                (string) ($log->http_method ?? ''),
                (string) ($log->event_type ?? ''),
                (string) ($log->action ?? ''),
                (string) ($log->request_uri ?? ''),
                (string) ($log->target_table ?? ''),
                (string) ($log->target_id ?? ''),
                (string) ($log->user_agent ?? ''),
                $this->jsonCell($log->old_values),
                $this->jsonCell($log->new_values),
            ];
        })->all();

        $binary = SimpleExcelWriter::toXls($headers, $rows, 'Audit logs');
        $filename = 'audit-logs-'.now()->format('Ymd-His').'.xls';

        $audit->log('Exported audit logs to Excel', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => 'audit_export',
            'http_method' => 'GET',
            'request_uri' => $request->path(),
            'new_values' => [
                'exported_rows' => count($rows),
                'matched_total' => $total,
                'limit' => $limit,
                'filename' => $filename,
            ],
        ]);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Export-Rows' => (string) count($rows),
            'X-Export-Total' => (string) $total,
            'X-Export-Truncated' => $total > count($rows) ? '1' : '0',
        ]);
    }

    public function resolve(
        ResolveSuspiciousAuditLogRequest $request,
        AuditLog $auditLog,
        AuditLogService $audit,
    ): JsonResponse {
        if (! Schema::hasColumn('audit_logs', 'suspicious_resolved_at')) {
            throw ValidationException::withMessages([
                'audit_log' => ['Suspicious resolution is not available until migrations are applied.'],
            ]);
        }

        if (! $auditLog->is_suspicious) {
            throw ValidationException::withMessages([
                'audit_log' => ['This audit log is not flagged as suspicious.'],
            ]);
        }

        if ($auditLog->suspicious_resolved_at !== null) {
            return response()->json([
                'message' => 'Already resolved.',
                'data' => $this->transform($auditLog->loadMissing(['resolver:id,name,email', 'externalIntegration:id,name,slug'])),
            ]);
        }

        $note = trim((string) $request->validated('note', ''));
        $actor = $request->user();

        $auditLog->update([
            'suspicious_resolved_at' => now(),
            'suspicious_resolved_by' => $actor?->id,
            'suspicious_resolution_note' => $note !== '' ? $note : null,
        ]);

        $audit->log('Resolved suspicious audit log', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => 'audit_suspicious_resolved',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'audit_logs',
            'target_id' => $auditLog->id,
            'new_values' => [
                'audit_log_id' => $auditLog->id,
                'note' => $note !== '' ? $note : null,
            ],
        ]);

        return response()->json([
            'message' => 'Suspicious event marked as resolved.',
            'data' => $this->transform($auditLog->fresh(['resolver:id,name,email', 'externalIntegration:id,name,slug'])),
        ]);
    }

    public function resolveOpenSuspicious(
        ResolveSuspiciousAuditLogRequest $request,
        AuditLogService $audit,
    ): JsonResponse {
        if (! Schema::hasColumn('audit_logs', 'suspicious_resolved_at')) {
            throw ValidationException::withMessages([
                'audit_logs' => ['Suspicious resolution is not available until migrations are applied.'],
            ]);
        }

        $note = trim((string) $request->validated('note', ''));
        $actor = $request->user();
        $now = now();

        $query = AuditLog::query()->unresolvedSuspicious();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        $ids = (clone $query)->pluck('id');
        $count = $ids->count();

        if ($count > 0) {
            AuditLog::query()
                ->whereIn('id', $ids->all())
                ->update([
                    'suspicious_resolved_at' => $now,
                    'suspicious_resolved_by' => $actor?->id,
                    'suspicious_resolution_note' => $note !== '' ? $note : null,
                    'updated_at' => $now,
                ]);
        }

        $audit->log('Resolved open suspicious audit logs', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => 'audit_suspicious_resolved_bulk',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'audit_logs',
            'new_values' => [
                'resolved_count' => $count,
                'note' => $note !== '' ? $note : null,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);

        return response()->json([
            'message' => $count > 0
                ? "Resolved {$count} suspicious event".($count === 1 ? '' : 's').'.'
                : 'No open suspicious events to resolve.',
            'data' => ['resolved_count' => $count],
        ]);
    }

    /**
     * @return Builder<AuditLog>
     */
    protected function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()
            ->with(['externalIntegration:id,name,slug'])
            ->orderByDesc('id');

        $search = trim((string) $request->query('search', $request->query('q', '')));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('action', 'like', $like)
                    ->orWhere('user_name', 'like', $like)
                    ->orWhere('user_email', 'like', $like)
                    ->orWhere('request_uri', 'like', $like)
                    ->orWhere('target_table', 'like', $like)
                    ->orWhere('target_id', 'like', $like)
                    ->orWhere('event_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('suspicious_reasons', 'like', $like)
                    ->orWhere('actor_type', 'like', $like);
            });
        }

        if ($request->filled('name')) {
            $query->where('user_name', 'like', '%'.trim((string) $request->query('name')).'%');
        }

        if ($request->filled('email')) {
            $query->where('user_email', 'like', '%'.trim((string) $request->query('email')).'%');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%'.trim((string) $request->query('ip_address')).'%');
        }

        if ($request->filled('http_method')) {
            $query->where('http_method', strtoupper((string) $request->query('http_method')));
        }

        if ($request->filled('event_type')) {
            $eventType = trim((string) $request->query('event_type'));
            if ($request->boolean('event_type_exact')) {
                $query->where('event_type', $eventType);
            } else {
                $query->where('event_type', 'like', '%'.$eventType.'%');
            }
        }

        if ($request->filled('target_table')) {
            $query->where('target_table', trim((string) $request->query('target_table')));
        }

        if (Schema::hasColumn('audit_logs', 'actor_type') && $request->filled('actor_type')) {
            $query->where('actor_type', trim((string) $request->query('actor_type')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        if (Schema::hasColumn('audit_logs', 'is_suspicious') && $request->filled('suspicious')) {
            $suspicious = $request->query('suspicious');
            if ($suspicious === '1' || $suspicious === 1 || $suspicious === true || $suspicious === 'true') {
                $query->where('is_suspicious', true);
            } elseif ($suspicious === '0' || $suspicious === 0 || $suspicious === false || $suspicious === 'false') {
                $query->where('is_suspicious', false);
            }
        }

        if (Schema::hasColumn('audit_logs', 'suspicious_resolved_at') && $request->filled('resolved')) {
            $resolved = $request->query('resolved');
            if ($resolved === '1' || $resolved === 1 || $resolved === true || $resolved === 'true') {
                $query->where('is_suspicious', true)->whereNotNull('suspicious_resolved_at');
            } elseif ($resolved === '0' || $resolved === 0 || $resolved === false || $resolved === 'false') {
                $query->where('is_suspicious', true)->whereNull('suspicious_resolved_at');
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(AuditLog $log): array
    {
        $isSuspicious = (bool) ($log->is_suspicious ?? false);
        $resolvedAt = $log->suspicious_resolved_at;

        return [
            'id' => $log->id,
            'actor_type' => $log->actor_type ?? AuditLogService::ACTOR_SYSTEM_USER,
            'actor_label' => match ($log->actor_type ?? AuditLogService::ACTOR_SYSTEM_USER) {
                AuditLogService::ACTOR_CLIENT => 'Client',
                AuditLogService::ACTOR_SYSTEM => 'System',
                default => 'System user',
            },
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'user_email' => $log->user_email,
            'external_integration_id' => $log->external_integration_id,
            'external_integration' => $log->externalIntegration ? [
                'id' => $log->externalIntegration->id,
                'name' => $log->externalIntegration->name,
                'slug' => $log->externalIntegration->slug,
            ] : null,
            'action' => $log->action,
            'event_type' => $log->event_type,
            'http_method' => $log->http_method,
            'request_uri' => $log->request_uri,
            'target_table' => $log->target_table,
            'target_id' => $log->target_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'is_suspicious' => $isSuspicious,
            'suspicious_reasons' => $log->suspicious_reasons,
            'suspicious_resolved' => $isSuspicious && $resolvedAt !== null,
            'suspicious_open' => $isSuspicious && $resolvedAt === null,
            'suspicious_resolved_at' => $resolvedAt?->toIso8601String(),
            'suspicious_resolution_note' => $log->suspicious_resolution_note,
            'suspicious_resolved_by' => $log->resolver ? [
                'id' => $log->resolver->id,
                'name' => $log->resolver->name,
                'email' => $log->resolver->email,
            ] : null,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    protected function jsonCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        try {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) {
            return '';
        }
    }
}
