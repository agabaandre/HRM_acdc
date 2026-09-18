<?php

namespace Modules\Performance\Support;

use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

/**
 * Who may configure PPA / performance deadlines and workflow flags.
 *
 * - Permission 15 (settings / view_reports) — existing portal gate
 * - Role 10 System Administrator
 * - Role 20 HR Manager
 * - Role 22 HR Admin
 */
final class PerformanceSettingsAccess
{
    public const ROLE_SYSTEM_ADMIN = 10;

    public const ROLE_HR_MANAGER = 20;

    public const ROLE_HR_ADMIN = 22;

    public static function roleId(): int
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            return (int) $user->role;
        }

        return (int) (session('user.role_id') ?? session('user.role') ?? 0);
    }

    public static function canManage(): bool
    {
        if (PortalPermission::can(15)) {
            return true;
        }

        return in_array(self::roleId(), [
            self::ROLE_SYSTEM_ADMIN,
            self::ROLE_HR_MANAGER,
            self::ROLE_HR_ADMIN,
        ], true);
    }

    public static function authorize(): void
    {
        if (! self::canManage()) {
            abort(403, 'You do not have permission to manage performance settings.');
        }
    }
}
