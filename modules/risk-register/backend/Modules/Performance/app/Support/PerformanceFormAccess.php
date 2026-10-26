<?php

namespace Modules\Performance\Support;

use Modules\Core\Support\PortalPermission;
use Modules\Performance\Enums\PerformancePhase;

/**
 * Who may open another staff member's PPA and send it back to the employee.
 *
 * Permission 83 is the existing allow_return_ppa grant. HR Manager / HR Admin /
 * System Administrator may view any entry so they can use that grant.
 *
 * Permission 127 (change_performance_supervisors) is required to change
 * supervisors on draft forms — employees do not get this by default.
 */
final class PerformanceFormAccess
{
    public static function canViewAnyEntry(): bool
    {
        return PortalPermission::can(PerformancePermissions::ALLOW_RETURN)
            || PerformanceSettingsAccess::canManage();
    }

    public static function canReturnOverride(): bool
    {
        return PortalPermission::can(PerformancePermissions::ALLOW_RETURN);
    }

    public static function phaseIsDraft(?object $entry, PerformancePhase $phase): bool
    {
        if (! $entry) {
            return $phase === PerformancePhase::Ppa;
        }

        if ($phase === PerformancePhase::Endterm && ($entry->overall_end_term_status ?? '') === 'Approved') {
            return false;
        }

        return (int) ($entry->{$phase->draftStatusColumn()} ?? 1) === 1;
    }

    public static function canChangeSupervisors(?object $entry, PerformancePhase $phase, int $actorStaffId = 0): bool
    {
        if (! self::phaseIsDraft($entry, $phase)) {
            return false;
        }

        return PortalPermission::can(PerformancePermissions::CHANGE_SUPERVISORS);
    }
}
