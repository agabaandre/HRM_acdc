<?php

namespace Modules\Leave\Support;

use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class LeaveAccess
{
    public static function isHr(): bool
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            return (int) $user->role === 20;
        }

        $role = session('user.role_id') ?? session('user.role') ?? null;

        return (int) $role === 20;
    }

    public static function staffId(): ?int
    {
        $user = auth()->user();
        if ($user instanceof PortalUser && $user->auth_staff_id) {
            return (int) $user->auth_staff_id;
        }

        $id = session('user.staff_id') ?? null;

        return $id ? (int) $id : null;
    }

    public static function canMakeRequest(): bool
    {
        // Soft BC: linked staff can apply even before 37 is assigned; prefer assigning 37.
        return PortalPermission::can(LeavePermissions::MAKE_REQUEST)
            || self::isHr()
            || self::staffId() !== null;
    }

    public static function canApprove(): bool
    {
        return PortalPermission::can(LeavePermissions::APPROVE_REQUEST) || self::isHr();
    }

    public static function canViewAllStaffRequests(): bool
    {
        if (self::isHr()) {
            return true;
        }

        if (PortalPermission::can(LeavePermissions::VIEW_ALL)) {
            return true;
        }

        // Backward compatible: CI3 overloaded domain_controller (77) for this.
        return PortalPermission::can(LeavePermissions::LEGACY_VIEW_ALL);
    }

    public static function canManageBalances(): bool
    {
        return PortalPermission::can(LeavePermissions::MANAGE_BALANCES) || self::isHr();
    }

    public static function canManageSettings(): bool
    {
        return PortalPermission::can(LeavePermissions::MANAGE_SETTINGS)
            || PortalPermission::can(15)
            || self::isHr();
    }

    public static function canManageHolidays(): bool
    {
        return PortalPermission::can(LeavePermissions::MANAGE_HOLIDAYS)
            || self::canManageSettings();
    }

    public static function canAccessModule(): bool
    {
        if (self::isHr()) {
            return true;
        }

        foreach (LeavePermissions::moduleAccessIds() as $id) {
            if (PortalPermission::can($id)) {
                return true;
            }
        }

        // Linked staff may open self-service leave even before 37 is assigned (legacy soft access).
        return self::staffId() !== null;
    }

    public static function authorizeBalancesAdmin(): void
    {
        if (! self::canManageBalances()) {
            abort(403, 'You do not have permission to manage leave balances.');
        }
    }

    public static function authorizeHolidays(): void
    {
        if (! self::canManageHolidays()) {
            abort(403, 'You do not have permission to manage leave holidays.');
        }
    }

    public static function authorizeSettings(): void
    {
        if (! self::canManageSettings()) {
            abort(403, 'You do not have permission to manage leave settings.');
        }
    }
}
