<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItSetting extends Model
{
    public const KEY_LICENSE_EXPIRY_ALERT_ENABLED = 'license_expiry_alert_enabled';

    public const KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS = 'license_expiry_alert_interval_days';

    public const KEY_LICENSE_EXPIRY_ALERT_INTERVALS = 'license_expiry_alert_intervals';

    public const KEY_LICENSE_EXPIRY_ALERT_LAST_RUN_AT = 'license_expiry_alert_last_run_at';

    protected $table = 'it_settings';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function putValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function licenseExpiryAlertEnabled(): bool
    {
        $value = static::getValue(self::KEY_LICENSE_EXPIRY_ALERT_ENABLED, '1');

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    /**
     * @return list<int>
     */
    public static function licenseExpiryAlertIntervals(): array
    {
        $raw = (string) static::getValue(self::KEY_LICENSE_EXPIRY_ALERT_INTERVALS, '1,3,7');
        $days = array_values(array_unique(array_filter(array_map(
            fn ($part) => (int) trim($part),
            explode(',', $raw),
        ), fn (int $day) => $day >= 1 && $day <= 365)));
        sort($days);

        return $days !== [] ? $days : [1, 3, 7];
    }

    public static function licenseExpiryAlertIntervalDays(): int
    {
        $intervals = static::licenseExpiryAlertIntervals();
        $active = (int) static::getValue(self::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS, '7');
        if (! in_array($active, $intervals, true)) {
            return $intervals[0];
        }

        return $active;
    }
}
