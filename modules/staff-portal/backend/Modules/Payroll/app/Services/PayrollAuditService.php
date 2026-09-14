<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\PayrollAuditLog;

class PayrollAuditService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, string $entityType, ?int $entityId, ?array $before = null, ?array $after = null): void
    {
        $userId = auth()->id();

        PayrollAuditLog::query()->create([
            'actor_user_id' => $userId ? (int) $userId : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'created_at' => now(),
        ]);
    }
}
