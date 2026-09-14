<?php

namespace Modules\Performance\Support;

use Modules\Core\Support\PortalPermission;
use Modules\Performance\Enums\PerformancePhase;

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

    public static function canChangeSupervisors(?object $entry, PerformancePhase $phase, int $actorStaffId): bool
    {
        if (! self::phaseIsDraft($entry, $phase)) {
            return false;
        }

        if (! $entry) {
            return true;
        }

        return $actorStaffId === (int) $entry->staff_id || self::canReturnOverride();
    }
}
