<?php

namespace Modules\Leave\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class LeaveAccess
{
    /** System Administrator, HR Manager, HR Admin — groups that receive permission 96. */
    public const BALANCES_ADMIN_ROLES = [10, 20, 22];

    public static function isHr(): bool
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            return (int) $user->role === 20;
        }

        $role = session('user.role_id') ?? session('user.role') ?? null;

        return (int) $role === 20;
    }

    public static function currentRoleId(): int
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            return (int) $user->role;
        }

        return (int) (session('user.role_id') ?? session('user.role') ?? 0);
    }

    public static function isBalancesAdminRole(?int $roleId): bool
    {
        return in_array((int) $roleId, self::BALANCES_ADMIN_ROLES, true);
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
        return PortalPermission::can(LeavePermissions::MANAGE_BALANCES);
    }

    /**
     * Grant manage_leave_balances (96) to System Administrator + HR groups only.
     * Removes it from every other group and from per-user grants on other roles.
     */
    public static function syncManageBalancesPermission(): void
    {
        $permId = LeavePermissions::MANAGE_BALANCES;
        $groupIds = self::BALANCES_ADMIN_ROLES;

        if (Schema::hasTable('permissions')) {
            $row = [
                'id' => $permId,
                'name' => 'manage_leave_balances',
                'definition' => 'Manage Leave Balances',
                'module' => 'leave',
            ];
            $existing = DB::table('permissions')->where('id', $permId)->first();
            if ($existing) {
                DB::table('permissions')->where('id', $permId)->update([
                    'name' => $row['name'],
                    'definition' => $row['definition'],
                    'module' => $row['module'],
                ]);
            } elseif (! DB::table('permissions')->where('name', $row['name'])->exists()) {
                DB::table('permissions')->insert($row);
            }
        }

        if (Schema::hasTable('user_groups') && Schema::hasTable('group_permissions')) {
            foreach ($groupIds as $groupId) {
                if (! DB::table('user_groups')->where('id', $groupId)->exists()) {
                    continue;
                }
                $exists = DB::table('group_permissions')
                    ->where('group_id', $groupId)
                    ->where('permission_id', $permId)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('group_permissions')->insert([
                    'group_id' => $groupId,
                    'permission_id' => $permId,
                    'last_updated' => now(),
                ]);
            }

            DB::table('group_permissions')
                ->where('permission_id', $permId)
                ->whereNotIn('group_id', $groupIds)
                ->delete();
        }

        if (Schema::hasTable('user_permissions') && Schema::hasTable('user')) {
            $keepUserIds = DB::table('user')
                ->whereIn('role', $groupIds)
                ->pluck('user_id');

            $query = DB::table('user_permissions')->where('permission_id', $permId);
            if ($keepUserIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $keepUserIds);
            }
            $query->delete();
        }
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
