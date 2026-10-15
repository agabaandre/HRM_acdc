<?php

namespace Modules\Staff\Support;

use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class StaffAccess
{
    public static function canViewDirectory(): bool
    {
        return PortalPermission::can(72) || PortalPermission::can(41);
    }

    public static function canViewProfile(int $staffId): bool
    {
        if (PortalPermission::can(71) || PortalPermission::can(72)) {
            return true;
        }

        $sessionStaffId = self::currentStaffId();

        return $sessionStaffId > 0 && $sessionStaffId === $staffId;
    }

    public static function canManageStaff(): bool
    {
        return PortalPermission::can(71);
    }

    public static function canManageContracts(): bool
    {
        return PortalPermission::can(71);
    }

    public static function currentStaffId(): int
    {
        $user = auth()->user();
        if ($user instanceof PortalUser && $user->auth_staff_id) {
            return (int) $user->auth_staff_id;
        }

        return (int) (session('user.staff_id') ?? 0);
    }
}
