<?php

namespace Modules\Staff\Support;

class StaffAccess
{
    public static function canViewDirectory(): bool
    {
        return portal_can(72) || portal_can(41);
    }

    public static function canViewProfile(int $staffId): bool
    {
        if (portal_can(71) || portal_can(72)) {
            return true;
        }
        $sessionStaffId = (int) (session('user.staff_id') ?? 0);

        return $sessionStaffId > 0 && $sessionStaffId === $staffId;
    }

    public static function canManageStaff(): bool
    {
        return portal_can(71);
    }

    public static function canManageContracts(): bool
    {
        return portal_can(71);
    }
}
