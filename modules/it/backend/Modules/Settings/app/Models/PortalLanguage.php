<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

use Modules\Settings\Support\PortalLocalesConfig;

class PortalLanguage extends Model
{
    public const CACHE_KEY_SELECTOR = 'portal_languages.selector_map';

    public const CACHE_KEY_ACTIVE_CODES = 'portal_languages.active_locale_codes';

    public const CACHE_KEY_ALL_CODES = 'portal_languages.all_locale_codes';

    protected $table = 'portal_languages';

    protected $fillable = [
        'locale_code',
        'name',
        'google_translate_code',
        'flag_emoji',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            static::flushCache();
        });
        static::deleted(static function (): void {
            static::flushCache();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_SELECTOR);
        Cache::forget(self::CACHE_KEY_ACTIVE_CODES);
        Cache::forget(self::CACHE_KEY_ALL_CODES);
    }

    /**
     * @return array<string, array{name: string, flag: string, code: string, google_code: string}>
     */
    public static function fallbackSelectorMap(): array
    {
        $cfg = PortalLocalesConfig::get('languages', []);
        $out = [];
        foreach ($cfg as $code => $row) {
            if (! is_string($code) || $code === '' || ! is_array($row)) {
                continue;
            }
            $out[$code] = [
                'name' => (string) ($row['name'] ?? $code),
                'flag' => (string) ($row['flag'] ?? ''),
                'code' => $code,
                'google_code' => (string) ($row['google_code'] ?? $code),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{name: string, flag: string, code: string, google_code: string}>
     */
    public static function selectorMap(): array
    {
        return Cache::remember(self::CACHE_KEY_SELECTOR, 3600, function () {
            if (! Schema::hasTable('portal_languages')) {
                return self::fallbackSelectorMap();
            }

            $rows = static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['locale_code', 'name', 'google_translate_code', 'flag_emoji']);

            if ($rows->isEmpty()) {
                return self::fallbackSelectorMap();
            }

            $out = [];
            foreach ($rows as $row) {
                $code = (string) $row->locale_code;
                $out[$code] = [
                    'name' => (string) $row->name,
                    'flag' => (string) ($row->flag_emoji ?? ''),
                    'code' => $code,
                    'google_code' => (string) ($row->google_translate_code ?: $code),
                ];
            }

            return $out;
        });
    }

    /**
     * @return list<string>
     */
    public static function activeLocaleCodes(): array
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE_CODES, 3600, function () {
            if (! Schema::hasTable('portal_languages')) {
                return array_keys(self::fallbackSelectorMap());
            }

            $codes = static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('locale_code')
                ->all();

            return $codes !== [] ? array_values($codes) : array_keys(self::fallbackSelectorMap());
        });
    }

    /**
     * @return list<string>
     */
    public static function allLocaleCodes(): array
    {
        return Cache::remember(self::CACHE_KEY_ALL_CODES, 3600, function () {
            if (! Schema::hasTable('portal_languages')) {
                return array_keys(self::fallbackSelectorMap());
            }

            $codes = static::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('locale_code')
                ->all();

            return $codes !== [] ? array_values($codes) : array_keys(self::fallbackSelectorMap());
        });
    }
}
