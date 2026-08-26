<?php

namespace Modules\Performance\Support;

use Modules\Core\Support\PortalPermission;

/**
 * Who may open another staff member's PPA and send it back to the employee.
 *
 * Permission 83 is the existing allow_return_ppa grant. HR Manager / HR Admin /
 * System Administrator may view any entry so they can use that grant.
 */
final class PerformanceFormAccess
{
    public static function canViewAnyEntry(): bool
    {
        return PortalPermission::can(83) || PerformanceSettingsAccess::canManage();
    }

    public static function canReturnOverride(): bool
    {
        return PortalPermission::can(83);
    }
}
