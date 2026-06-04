<?php

namespace Modules\Settings\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\PortalTable;

class SettingsLookupService
{
    /**
     * @return array<string, mixed>|null
     */
    public function config(string $table): ?array
    {
        $all = config('settings.lookup-tables', []);

        return $all[$table] ?? null;
    }

    /**
     * @return list<object>
     */
    public function list(string $table, string $search = ''): array
    {
        return $this->paginate($table, $search, 500, 1)->items();
    }

    public function paginate(
        string $table,
        string $search = '',
        int $perPage = 20,
        ?int $page = null
    ): LengthAwarePaginator {
        $cfg = $this->config($table);
        if ($cfg === null || ! Schema::hasTable($table)) {
            return new LengthAwarePaginator([], 0, $perPage, $page ?? 1);
        }

        $q = DB::table($table);
        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($cfg, $term): void {
                foreach (array_keys($cfg['columns']) as $col) {
                    $w->orWhere($col, 'like', $term);
                }
            });
        }

        $pk = $cfg['pk'];
        $q->orderBy($cfg['order'] ?? $pk);

        return PortalTable::paginateDistinct($q, $table.'.'.$pk, $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $table, array $data): bool
    {
        $cfg = $this->config($table);
        if ($cfg === null) {
            return false;
        }

        $payload = $this->filterPayload($cfg, $data);
        if ($payload === []) {
            return false;
        }

        return DB::table($table)->insert($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $table, int|string $id, array $data): bool
    {
        $cfg = $this->config($table);
        if ($cfg === null) {
            return false;
        }

        $payload = $this->filterPayload($cfg, $data);

        return DB::table($table)->where($cfg['pk'], $id)->update($payload) > 0;
    }

    public function delete(string $table, int|string $id): bool
    {
        $cfg = $this->config($table);
        if ($cfg === null) {
            return false;
        }

        return DB::table($table)->where($cfg['pk'], $id)->delete() > 0;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filterPayload(array $cfg, array $data): array
    {
        $payload = [];
        foreach ($cfg['columns'] as $col => $meta) {
            if (! array_key_exists($col, $data)) {
                continue;
            }
            $type = $meta['type'] ?? 'text';
            if ($type === 'checkbox') {
                $payload[$col] = ! empty($data[$col]) ? 1 : 0;
            } elseif ($type === 'number') {
                $payload[$col] = $data[$col] === '' || $data[$col] === null ? null : (int) $data[$col];
            } else {
                $payload[$col] = $data[$col];
            }
        }

        return $payload;
    }
}
