<?php

namespace Modules\Core\Support;

use Modules\Auth\Models\PortalUser;

class PortalPermission
{
    public static function can(int|string $permissionId): bool
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            // Prefer session copy (no DB) then lightweight permissionIds() — avoid full toSessionArray().
            $sessionPerms = session('user.permissions');
            if (is_array($sessionPerms) && $sessionPerms !== []) {
                return PortalNavigation::can($sessionPerms, $permissionId);
            }

            return PortalNavigation::can($user->permissionIds(), $permissionId);
        }

        return portal_can($permissionId);
    }

    public static function authorize(int|string $permissionId): void
    {
        if (! self::can($permissionId)) {
            abort(403, 'You do not have permission for this action.');
        }
    }
}
