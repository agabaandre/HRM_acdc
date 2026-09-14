<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    /** @var array<string, list<string>> */
    private array $columnCache = [];

    /**
     * @return list<string>
     */
    public function getAuditTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $auditTables = [];

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            if ((str_starts_with($tableName, 'audit_') && str_contains($tableName, '_logs'))
                || $tableName === 'audit_logs'
                || str_contains($tableName, '_audit')) {
                $auditTables[] = $tableName;
            }
        }

        sort($auditTables);

        return $auditTables;
    }

    public function getDistinctActions(): Collection
    {
        $actions = collect();

        foreach ($this->getAuditTables() as $table) {
            if (! $this->hasColumn($table, 'action')) {
                continue;
            }

            $actions = $actions->merge(
                DB::table($table)->distinct()->orderBy('action')->pluck('action')
            );
        }

        return $actions->filter()->unique()->sort()->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(Request $request): array
    {
        $base = $this->baseQuery($request);
        $total = (clone $base)->count();

        $recent = (clone $base)
            ->where('audit_union.created_at', '>=', Carbon::now()->subHours(24))
            ->count();

        $topActionRow = (clone $base)
            ->select('audit_union.action', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('audit_union.action')
            ->orderByDesc('aggregate')
            ->first();

        $topTableRow = (clone $base)
            ->select('audit_union.source_table', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('audit_union.source_table')
            ->orderByDesc('aggregate')
            ->first();

        $actionsCount = (clone $base)
            ->select('audit_union.action', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('audit_union.action')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'action');

        $tablesCount = (clone $base)
            ->select('audit_union.source_table', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('audit_union.source_table')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'source_table');

        return [
            'total_logs' => $total,
            'recent_activity' => $recent,
            'actions_count' => $actionsCount,
            'tables_count' => $tablesCount,
            'top_action' => $topActionRow->action ?? 'N/A',
            'top_action_count' => (int) ($topActionRow->aggregate ?? 0),
            'top_table' => $topTableRow->source_table ?? 'N/A',
            'top_table_count' => (int) ($topTableRow->aggregate ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paginateForDataTable(Request $request): array
    {
        $base = $this->baseQuery($request);
        $total = (clone $base)->count();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', $request->input('per_page', 25));
        $length = min(100, max(10, $length > 0 ? $length : 25));

        $rows = (clone $base)
            ->orderByDesc('audit_union.created_at')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn ($row) => $this->enrichRow($row));

        return [
            'success' => true,
            'draw' => (int) $request->input('draw', 1),
            'data' => $rows->values()->all(),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'stats' => $this->getStats($request),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function exportRows(Request $request, int $limit = 50000): Collection
    {
        return (clone $this->baseQuery($request))
            ->orderByDesc('audit_union.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->enrichRow($row));
    }

    public function baseQuery(Request $request): Builder
    {
        $tables = $this->getAuditTables();

        if ($request->filled('table')) {
            $tables = array_values(array_intersect($tables, [(string) $request->get('table')]));
        }

        $parts = [];
        foreach ($tables as $table) {
            $select = $this->buildTableSelect($table);
            if ($select !== null) {
                $parts[] = $select;
            }
        }

        if ($parts === []) {
            return DB::table(DB::raw(
                '(SELECT NULL as id, NULL as action, NULL as entity_id, NULL as source_table, '
                .'NULL as causer_id, NULL as causer_type, NULL as old_values, NULL as new_values, '
                .'NULL as metadata, NULL as source, NULL as user_name, NULL as user_email, '
                .'NULL as description, NULL as created_at LIMIT 0) as audit_union'
            ))->leftJoin('staff as s', 's.staff_id', '=', 'audit_union.causer_id');
        }

        $unionSql = implode(' UNION ALL ', $parts);

        $query = DB::table(DB::raw("({$unionSql}) as audit_union"))
            ->leftJoin('staff as s', 's.staff_id', '=', 'audit_union.causer_id')
            ->select([
                'audit_union.id',
                'audit_union.action',
                'audit_union.entity_id',
                'audit_union.source_table',
                'audit_union.causer_id',
                'audit_union.causer_type',
                'audit_union.old_values',
                'audit_union.new_values',
                'audit_union.metadata',
                'audit_union.source',
                'audit_union.user_name',
                'audit_union.user_email',
                'audit_union.description',
                'audit_union.created_at',
                DB::raw("NULLIF(TRIM(CONCAT(COALESCE(s.fname,''), ' ', COALESCE(s.lname,''))), '') as causer_name"),
                DB::raw('s.work_email as causer_email'),
                DB::raw('s.job_name as causer_job_title'),
                DB::raw('s.division_name as causer_division_name'),
                DB::raw('s.duty_station_name as causer_duty_station_name'),
            ]);

        $this->applyFilters($query, $request);

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $like = '%'.$search.'%';

            $staffIds = DB::table('staff')
                ->where(function ($q) use ($like): void {
                    $q->where('fname', 'like', $like)
                        ->orWhere('lname', 'like', $like)
                        ->orWhere('work_email', 'like', $like)
                        ->orWhereRaw("CONCAT(fname, ' ', lname) LIKE ?", [$like]);
                })
                ->pluck('staff_id');

            $query->where(function ($q) use ($like, $staffIds): void {
                $q->where('audit_union.action', 'like', $like)
                    ->orWhere('audit_union.source_table', 'like', $like)
                    ->orWhere('audit_union.entity_id', 'like', $like)
                    ->orWhere('audit_union.user_name', 'like', $like)
                    ->orWhere('audit_union.user_email', 'like', $like)
                    ->orWhere('audit_union.description', 'like', $like)
                    ->orWhere('s.fname', 'like', $like)
                    ->orWhere('s.lname', 'like', $like)
                    ->orWhere('s.work_email', 'like', $like)
                    ->orWhereRaw("CONCAT(s.fname, ' ', s.lname) LIKE ?", [$like]);

                if ($staffIds->isNotEmpty()) {
                    $q->orWhereIn('audit_union.causer_id', $staffIds);
                }
            });
        }

        if ($request->filled('action')) {
            $query->where('audit_union.action', $request->get('action'));
        }

        if ($request->filled('date_from')) {
            $query->where(
                'audit_union.created_at',
                '>=',
                Carbon::parse((string) $request->get('date_from'))->startOfDay()
            );
        }

        if ($request->filled('date_to')) {
            $query->where(
                'audit_union.created_at',
                '<=',
                Carbon::parse((string) $request->get('date_to'))->endOfDay()
            );
        }

        if ($request->filled('suspicious')) {
            if ($request->get('suspicious') === '1') {
                $query->where(function ($q): void {
                    $q->whereNull('audit_union.causer_id')
                        ->orWhereNull('s.staff_id');
                });
            } elseif ($request->get('suspicious') === '0') {
                $query->whereNotNull('audit_union.causer_id')
                    ->whereNotNull('s.staff_id');
            }
        }
    }

    private function buildTableSelect(string $table): ?string
    {
        if (! $this->hasColumn($table, 'created_at')) {
            return null;
        }

        $entity = $this->hasColumn($table, 'entity_id')
            ? 'CAST(`entity_id` AS CHAR)'
            : ($this->hasColumn($table, 'resource_id')
                ? 'CAST(`resource_id` AS CHAR)'
                : 'CAST(`id` AS CHAR)');

        $tableSql = str_replace('`', '``', $table);

        return 'SELECT `id`, '
            .$this->columnExpr($table, 'action', "'unknown'").' AS `action`, '
            .$entity.' AS `entity_id`, '
            ."'{$tableSql}' AS `source_table`, "
            .$this->columnExpr($table, 'causer_id').' AS `causer_id`, '
            .$this->columnExpr($table, 'causer_type').' AS `causer_type`, '
            .$this->columnExpr($table, 'old_values').' AS `old_values`, '
            .$this->columnExpr($table, 'new_values').' AS `new_values`, '
            .$this->columnExpr($table, 'metadata').' AS `metadata`, '
            .$this->columnExpr($table, 'source').' AS `source`, '
            .$this->columnExpr($table, 'user_name').' AS `user_name`, '
            .$this->columnExpr($table, 'user_email').' AS `user_email`, '
            .$this->columnExpr($table, 'description').' AS `description`, '
            .'`created_at` FROM `'.$tableSql.'`';
    }

    private function columnExpr(string $table, string $column, string $default = 'NULL'): string
    {
        return $this->hasColumn($table, $column) ? '`'.$column.'`' : $default;
    }

    /**
     * @return list<string>
     */
    private function getTableColumns(string $table): array
    {
        if (! isset($this->columnCache[$table])) {
            $this->columnCache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return $this->columnCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    private function enrichRow(object $row): object
    {
        if (empty(trim((string) ($row->causer_name ?? '')))) {
            $row->causer_name = trim((string) ($row->user_name ?? '')) ?: 'Unknown User';
        }

        if (empty($row->causer_email)) {
            $row->causer_email = $row->user_email ?? 'N/A';
        }

        $row->causer_job_title = $row->causer_job_title ?? 'N/A';
        $row->causer_division_name = $row->causer_division_name ?? 'N/A';
        $row->causer_duty_station_name = $row->causer_duty_station_name ?? 'N/A';

        $row->is_suspicious = ! $row->causer_id
            || $row->causer_name === 'Unknown User'
            || empty(trim((string) ($row->causer_name ?? '')));

        $row->suspicious_reasons = $row->is_suspicious
            ? (! $row->causer_id ? 'Unknown User' : 'Unlinked or unknown user')
            : '';

        return $row;
    }
}
