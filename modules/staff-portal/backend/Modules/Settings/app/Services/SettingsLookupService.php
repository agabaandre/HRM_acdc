<?php

namespace Modules\Settings\Services;

use App\Support\PortalReferenceCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Core\Support\PortalTable;

class SettingsLookupService
{
    /**
     * @return array<string, mixed>|null
     */
    public function config(string $table): ?array
    {
        $all = config('settings.lookup-tables', []);
        $cfg = $all[$table] ?? null;
        if ($cfg === null) {
            return null;
        }

        return $this->resolveSelectOptions($cfg);
    }

    /**
     * Expand columns that use options_from → options (value => label).
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    protected function resolveSelectOptions(array $cfg): array
    {
        $columns = $cfg['columns'] ?? [];
        if (! is_array($columns)) {
            return $cfg;
        }

        foreach ($columns as $col => $meta) {
            if (! is_array($meta) || ($meta['type'] ?? '') !== 'select') {
                continue;
            }
            $from = $meta['options_from'] ?? null;
            if (! is_array($from) || empty($from['table']) || empty($from['value']) || empty($from['label'])) {
                continue;
            }
            $optTable = (string) $from['table'];
            if (! Schema::hasTable($optTable)) {
                $columns[$col]['options'] = [];
                continue;
            }
            $valueCol = (string) $from['value'];
            $labelCol = (string) $from['label'];
            $orderCol = (string) ($from['order'] ?? $labelCol);
            $options = [];
            $rows = DB::table($optTable)->orderBy($orderCol)->get([$valueCol, $labelCol]);
            foreach ($rows as $row) {
                $key = (string) ($row->{$valueCol} ?? '');
                if ($key === '') {
                    continue;
                }
                $options[$key] = (string) ($row->{$labelCol} ?? $key);
            }
            $columns[$col]['options'] = $options;
        }

        $cfg['columns'] = $columns;

        return $cfg;
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

        $columns = Schema::getColumnListing($table);
        $pk = (string) ($cfg['pk'] ?? 'id');
        if (! in_array($pk, $columns, true)) {
            $pk = in_array('id', $columns, true) ? 'id' : ($columns[0] ?? 'id');
        }
        $order = (string) ($cfg['order'] ?? $pk);
        if (! in_array($order, $columns, true)) {
            $order = $pk;
        }

        $searchable = array_values(array_filter(
            array_keys($cfg['columns'] ?? []),
            static fn ($col) => in_array($col, $columns, true),
        ));

        $q = DB::table($table);
        if ($search !== '' && $searchable !== []) {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($searchable, $term): void {
                foreach ($searchable as $col) {
                    $w->orWhere($col, 'like', $term);
                }
            });
        }

        $q->orderBy($order);

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

        $ok = DB::table($table)->insert($payload);
        if ($ok) {
            PortalReferenceCache::bustLookup($table);
        }

        return $ok;
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

        $ok = DB::table($table)->where($cfg['pk'], $id)->update($payload) > 0;
        if ($ok) {
            PortalReferenceCache::bustLookup($table);
        }

        return $ok;
    }

    public function delete(string $table, int|string $id): bool
    {
        $cfg = $this->config($table);
        if ($cfg === null) {
            return false;
        }

        $ok = DB::table($table)->where($cfg['pk'], $id)->delete() > 0;
        if ($ok) {
            PortalReferenceCache::bustLookup($table);
        }

        return $ok;
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    public static function selectValueErrors(array $columns, array $data): array
    {
        $errors = [];
        foreach ($columns as $col => $meta) {
            if (! is_array($meta) || ($meta['type'] ?? '') !== 'select') {
                continue;
            }
            $options = $meta['options'] ?? null;
            if (! is_array($options) || $options === []) {
                continue;
            }
            if (! array_key_exists($col, $data)) {
                continue;
            }
            $value = $data[$col];
            $key = is_scalar($value) ? (string) $value : '';
            if (! array_key_exists($key, $options)) {
                $errors[$col] = ['The selected '.$col.' is invalid.'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filterPayload(array $cfg, array $data): array
    {
        $selectErrors = self::selectValueErrors($cfg['columns'] ?? [], $data);
        if ($selectErrors !== []) {
            throw ValidationException::withMessages($selectErrors);
        }

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
                // Legacy NOT NULL text columns — store blanks as empty strings.
                $payload[$col] = $data[$col] === null ? '' : $data[$col];
            }
        }

        return $payload;
    }
}
