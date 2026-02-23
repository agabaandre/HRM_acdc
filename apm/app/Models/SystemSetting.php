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

    protected $fillable = ['key', 'value', 'group'];

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
    public static function set(string $key, ?string $value, ?string $group = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
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
}
