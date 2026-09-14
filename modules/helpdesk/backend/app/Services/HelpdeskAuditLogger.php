<?php

namespace App\Services;

use App\Models\HelpdeskAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Append-only audit trail for security-sensitive actions (ISO/IEC 27001 friendly:
 * who, what, when, source; UTC timestamps; correlation for request tracing).
 */
class HelpdeskAuditLogger
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function log(
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): HelpdeskAuditLog {
        $user = $this->request->user();
        $profile = $user?->helpdeskProfile;

        $correlationId = $this->request->attributes->get('correlation_id')
            ?? $this->request->headers->get('X-Correlation-ID');

        $row = HelpdeskAuditLog::query()->create([
            'user_id' => $user?->id,
            'staff_id' => $profile?->staff_id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 512, ''),
            'correlation_id' => is_string($correlationId) && strlen($correlationId) <= 36 ? $correlationId : null,
            'old_values' => $oldValues,
            'new_values' => array_merge([
                '@timestamp' => now()->utc()->toIso8601String(),
            ], $newValues ?? []),
        ]);

        try {
            Log::channel('iso_json')->info('helpdesk.audit', [
                'event' => 'audit',
                'action' => $action,
                'user_id' => $user?->id,
                'staff_id' => $profile?->staff_id,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'correlation_id' => $row->correlation_id,
                'audit_log_id' => $row->id,
            ]);
        } catch (\Throwable) {
            // Optional channel — ignore if misconfigured
        }

        return $row;
    }
}
