<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group', 'type'];

    /**
     * Allowed field types for display/validation.
     */
    public const TYPES = ['text', 'password', 'number', 'boolean', 'color'];

    /**
     * Get a setting value by key. Uses cache for 1 hour.
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = 'system_setting:' . $key;
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $row = static::find($key);
            return $row ? $row->value : $default;
        });
    }

    /**
     * Set a setting value and clear cache.
     */
    public static function set(string $key, ?string $value, ?string $group = null, ?string $type = 'text'): void
    {
        $type = in_array($type, static::TYPES, true) ? $type : 'text';
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type]
        );
        Cache::forget('system_setting:' . $key);
    }

    /**
     * Get all settings as key => value (optionally by group).
     */
    public static function getAll(?string $group = null): array
    {
        $query = static::query();
        if ($group !== null) {
            $query->where('group', $group);
        }
        return $query->pluck('value', 'key')->toArray();
    }

    /**
     * Get all settings grouped by group, with key, value, type. For password type, value is null in output (do not expose).
     */
    public static function getGroupedForEditing(): array
    {
        $rows = static::orderBy('group')->orderBy('key')->get();
        $grouped = [];
        foreach ($rows as $row) {
            $group = $row->group ?? 'general';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $value = $row->value;
            $type = $row->type ?? 'text';
            if ($type === 'password' && $value !== null && $value !== '') {
                $value = null; // never send password value to view
            }
            $grouped[$group][] = [
                'key'   => $row->key,
                'value' => $value,
                'type'  => $type,
            ];
        }
        return $grouped;
    }
}
