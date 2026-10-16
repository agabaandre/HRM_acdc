<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

final class RiskAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
        ?int $actorStaffId = null,
    ): void {
        DB::table('rr_audit_logs')->insert([
            'actor_staff_id' => $actorStaffId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before === null ? null : json_encode($before),
            'after_json' => $after === null ? null : json_encode($after),
            'ip_address' => Request::ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
