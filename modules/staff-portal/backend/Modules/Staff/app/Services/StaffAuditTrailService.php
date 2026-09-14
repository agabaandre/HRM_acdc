<?php

namespace Modules\Staff\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Services\AuditLogService;

/**
 * Per-staff compliance trail (CI3 get_staff_profile_audit_trail parity).
 */
class StaffAuditTrailService
{
    /** @var list<string> */
    private const CONTRACT_AUDIT_KEYS = [
        'staff_contract_id',
        'staff_id',
        'job_id',
        'job_acting_id',
        'grade_id',
        'contracting_institution_id',
        'funder_id',
        'first_supervisor',
        'second_supervisor',
        'contract_type_id',
        'duty_station_id',
        'division_id',
        'other_associated_divisions',
        'unit_id',
        'start_date',
        'end_date',
        'status_id',
        'comments',
        'file_name',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
    ) {
    }

    public function structuredColumnsActive(): bool
    {
        return Schema::hasTable('user_logs')
            && Schema::hasColumn('user_logs', 'event_type')
            && Schema::hasColumn('user_logs', 'target_table')
            && Schema::hasColumn('user_logs', 'target_id');
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function logChange(
        string $eventSlug,
        string $targetTable,
        string|int $targetId,
        int $subjectStaffId,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        if ($subjectStaffId < 1 || ! $this->structuredColumnsActive()) {
            return;
        }

        $sid = (string) $subjectStaffId;
        $wrap = static function (?array $values) use ($sid): array {
            $arr = is_array($values) ? $values : [];
            $arr['_subject_staff_id'] = $sid;

            return $arr;
        };

        $this->audit->logRecordChange(
            $eventSlug,
            $targetTable,
            $targetId,
            $wrap($oldValues),
            $wrap($newValues),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function contractSnapshot(?object $row): array
    {
        if (! $row) {
            return [];
        }

        $out = [];
        foreach (self::CONTRACT_AUDIT_KEYS as $key) {
            if (property_exists($row, $key)) {
                $out[$key] = $row->{$key};
            }
        }

        return $out;
    }

    /**
     * Diff only keys present in $changedKeys (CI3 update_staff style).
     *
     * @param  list<string>  $changedKeys
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public function biodataSnapshots(?object $old, ?object $new, array $changedKeys): ?array
    {
        if (! $old || ! $new || $changedKeys === []) {
            return null;
        }

        $o = [];
        $n = [];
        foreach ($changedKeys as $key) {
            if ($key === 'staff_id' || ! is_string($key) || ! property_exists($old, $key)) {
                continue;
            }
            $o[$key] = $old->{$key};
            $n[$key] = property_exists($new, $key) ? $new->{$key} : null;
        }

        if ($o === [] || $this->snapshotsEqual($o, $n)) {
            return null;
        }

        return [$o, $n];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function snapshotsEqual(array $a, array $b): bool
    {
        return serialize($a) === serialize($b);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trailForStaff(int $staffId, int $limit = 100): array
    {
        if ($staffId < 1 || ! $this->structuredColumnsActive()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sidStr = (string) $staffId;
        $contractIds = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->pluck('staff_contract_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $select = [
            'user_logs.id',
            'user_logs.user_id',
            'user_logs.action',
            'user_logs.event_type',
            'user_logs.target_table',
            'user_logs.target_id',
        ];
        foreach (['created_at', 'http_method', 'request_uri', 'old_values', 'new_values'] as $col) {
            if (Schema::hasColumn('user_logs', $col)) {
                $select[] = "user_logs.{$col}";
            }
        }

        $query = DB::table('user_logs')
            ->leftJoin('user as u', 'u.user_id', '=', 'user_logs.user_id')
            ->leftJoin('staff as actor_staff', 'actor_staff.staff_id', '=', 'u.auth_staff_id')
            ->select(array_merge($select, [
                'u.name as actor_display_name',
                'actor_staff.work_email as actor_email',
            ]))
            ->where('user_logs.event_type', 'like', 'record_%')
            ->where(function ($q) use ($sidStr, $contractIds): void {
                $q->where(function ($inner) use ($sidStr): void {
                    $inner->where('user_logs.target_table', 'staff')
                        ->where('user_logs.target_id', $sidStr);
                });
                if ($contractIds !== []) {
                    $q->orWhere(function ($inner) use ($contractIds): void {
                        $inner->where('user_logs.target_table', 'staff_contracts')
                            ->whereIn('user_logs.target_id', $contractIds);
                    });
                }
                $q->orWhere(function ($inner) use ($sidStr): void {
                    $inner->where('user_logs.target_table', 'ppa_entries')
                        ->where('user_logs.target_id', $sidStr);
                });
            })
            ->orderByDesc(Schema::hasColumn('user_logs', 'created_at') ? 'user_logs.created_at' : 'user_logs.id')
            ->limit($limit)
            ->get();

        return $query->map(function (object $row): array {
            $old = $this->decodeJson($row->old_values ?? null);
            $new = $this->decodeJson($row->new_values ?? null);

            return [
                'id' => (int) ($row->id ?? 0),
                'user_id' => (int) ($row->user_id ?? 0),
                'actor_name' => trim((string) ($row->actor_display_name ?? ''))
                    ?: ('User #'.(int) ($row->user_id ?? 0)),
                'actor_email' => $row->actor_email ?? null,
                'created_at' => $row->created_at ?? null,
                'event_type' => $row->event_type ?? null,
                'target_table' => $row->target_table ?? null,
                'target_id' => $row->target_id ?? null,
                'target_label' => $this->targetLabel(
                    (string) ($row->target_table ?? ''),
                    (string) ($row->target_id ?? ''),
                ),
                'http_method' => $row->http_method ?? null,
                'request_uri' => $row->request_uri ?? null,
                'old_values' => $old,
                'new_values' => $new,
                'changes' => $this->diffRows($old, $new),
            ];
        })->all();
    }

    private function targetLabel(string $table, string $id): string
    {
        return match ($table) {
            'staff_contracts' => 'Contract #'.$id,
            'staff' => 'Staff profile',
            'ppa_entries' => 'PPA supervisors',
            default => trim($table.' #'.$id) ?: '—',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @return list<array{field: string, old: string, new: string, type: string}>
     */
    private function diffRows(?array $old, ?array $new): array
    {
        $old = $old ?? [];
        $new = $new ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $keys = array_values(array_filter(
            $keys,
            static fn ($k): bool => is_string($k) && $k !== '_subject_staff_id' && $k !== '_http_request',
        ));
        natcasesort($keys);

        $rows = [];
        foreach ($keys as $key) {
            $hasOld = array_key_exists($key, $old);
            $hasNew = array_key_exists($key, $new);
            $ov = $hasOld ? $old[$key] : null;
            $nv = $hasNew ? $new[$key] : null;
            if ($hasOld && $hasNew && serialize($ov) === serialize($nv)) {
                continue;
            }
            $type = ! $hasOld && $hasNew ? 'added' : ($hasOld && ! $hasNew ? 'removed' : 'changed');
            $rows[] = [
                'field' => (string) $key,
                'old' => $this->displayValue($ov),
                'new' => $this->displayValue($nv),
                'type' => $type,
            ];
        }

        return array_values($rows);
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            return $json === false ? '[array]' : $json;
        }

        return (string) $value;
    }
}
