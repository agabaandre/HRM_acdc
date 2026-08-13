<?php

namespace Modules\Payroll\Support;

use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class PayrollAccess
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

    public static function isAdmin(): bool
    {
        return PortalPermission::can(17);
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

    public static function hasOverride(): bool
    {
        return self::isHr() || self::isAdmin();
    }

    public static function can(int $permissionId): bool
    {
        return self::hasOverride() || PortalPermission::can($permissionId);
    }

    public static function canViewHub(): bool
    {
        if (self::hasOverride()) {
            return true;
        }

        foreach (PayrollPermissions::moduleAccessIds() as $id) {
            if (PortalPermission::can($id)) {
                return true;
            }
        }

        return false;
    }

    public static function canManageSetup(): bool
    {
        return self::can(PayrollPermissions::MANAGE_SETUP);
    }

    public static function canManageStaffPay(): bool
    {
        return self::can(PayrollPermissions::MANAGE_STAFF_PAY);
    }

    public static function canRunPayroll(): bool
    {
        return self::can(PayrollPermissions::RUN_PAYROLL);
    }

    public static function canManageLoans(): bool
    {
        return self::can(PayrollPermissions::MANAGE_LOANS);
    }

    public static function canApproveLoans(): bool
    {
        return self::can(PayrollPermissions::APPROVE_LOANS);
    }

    public static function canRequestLoan(): bool
    {
        return self::can(PayrollPermissions::REQUEST_LOAN) || self::staffId() !== null;
    }

    public static function canViewOwnPayslips(): bool
    {
        return self::can(PayrollPermissions::VIEW_OWN_PAYSLIPS) || self::staffId() !== null;
    }

    public static function canViewAnyPayslips(): bool
    {
        return self::canRunPayroll() || self::can(PayrollPermissions::VIEW_HUB) || self::hasOverride();
    }

    public static function authorizeModule(): void
    {
        if (! self::canViewHub()) {
            abort(403, 'You do not have permission to access payroll.');
        }
    }

    public static function authorizeSetup(): void
    {
        if (! self::canManageSetup()) {
            abort(403, 'You do not have permission to manage payroll setup.');
        }
    }

    public static function authorizeStaffPay(): void
    {
        if (! self::canManageStaffPay()) {
            abort(403, 'You do not have permission to manage staff pay.');
        }
    }

    public static function authorizeRun(): void
    {
        if (! self::canRunPayroll()) {
            abort(403, 'You do not have permission to run payroll.');
        }
    }

    public static function authorizeManageLoans(): void
    {
        if (! self::canManageLoans()) {
            abort(403, 'You do not have permission to manage loans.');
        }
    }

    public static function authorizeApproveLoans(): void
    {
        if (! self::canApproveLoans()) {
            abort(403, 'You do not have permission to approve loans.');
        }
    }
}
