<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Module control for APM Activity Reporting / Reporting Registry.
 * Moved off-platform — disabled by default until re-enabled in App settings → Module control.
 */
final class ActivityReportingModule
{
    public const ENABLED_KEY = 'module_activity_reporting_enabled';

    public const NOTIFICATIONS_KEY = 'module_activity_reporting_notifications_enabled';

    public static function isEnabled(): bool
    {
        return self::truthy(SystemSetting::get(self::ENABLED_KEY, '0'));
    }

    /**
     * Submit-reminder emails. Requires the module itself to be enabled.
     */
    public static function notificationsEnabled(): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        return self::truthy(SystemSetting::get(self::NOTIFICATIONS_KEY, '0'));
    }

    public static function assertEnabled(): void
    {
        if (! self::isEnabled()) {
            abort(404, 'Activity reporting is disabled in APM (module moved to another platform). Re-enable under App settings → Module control if needed.');
        }
    }

    private static function truthy(mixed $value): bool
    {
        $v = strtolower(trim((string) ($value ?? '0')));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
