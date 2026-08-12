<?php

namespace Modules\Audit\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogRevertService
{
    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    public function revert(int $logId, int|string|null $actorUserId = null): array
    {
        $log = DB::table('user_logs')->where('id', $logId)->first();

        if (! $log) {
            return $this->error(404, 'Audit log not found.');
        }

        if (! empty($log->reverted_at)) {
            return $this->error(422, 'This audit log has already been reverted.');
        }

        $table = trim((string) ($log->target_table ?? ''));
        $config = config("staff-portal.audit.revert_tables.{$table}");
        if ($table === '' || ! is_array($config)) {
            return $this->error(422, 'This audit log is not revertible.');
        }

        if (! Schema::hasTable($table)) {
            return $this->error(422, 'The target table no longer exists.');
        }

        $oldValues = json_decode((string) ($log->old_values ?? ''), true);
        if (! is_array($oldValues) || $oldValues === []) {
            return $this->error(422, 'No old_values snapshot is available to restore.');
        }

        $primaryKey = (string) ($config['pk'] ?? 'id');
        $targetId = $log->target_id ?? null;
        if ($targetId === null || $targetId === '') {
            return $this->error(422, 'This audit log is missing a target id.');
        }

        $payload = [];
        foreach (($config['columns'] ?? []) as $column) {
            if (! is_string($column) || ! array_key_exists($column, $oldValues) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $payload[$column] = $oldValues[$column];
        }

        if ($payload === []) {
            return $this->error(422, 'This audit log does not contain any restorable fields.');
        }

        if (! Schema::hasColumn($table, $primaryKey)) {
            return $this->error(422, 'The target table is not revertible.');
        }

        $targetExists = DB::table($table)->where($primaryKey, $targetId)->exists();
        if (! $targetExists) {
            return $this->error(422, 'The target record no longer exists.');
        }

        DB::transaction(function () use ($actorUserId, $logId, $payload, $primaryKey, $table, $targetId): void {
            DB::table($table)->where($primaryKey, $targetId)->update($payload);
            DB::table('user_logs')->where('id', $logId)->update([
                'reverted_at' => now(),
                'reverted_by_user_id' => $actorUserId === null || $actorUserId === ''
                    ? null
                    : (int) $actorUserId,
            ]);
        });

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'message' => 'Changes reverted from audit snapshot.',
            ],
        ];
    }

    /**
     * @return array{status:int, body:array{ok:false, message:string}}
     */
    protected function error(int $status, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'ok' => false,
                'message' => $message,
            ],
        ];
    }
}
