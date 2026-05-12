<?php

namespace App\Services;

use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;

/**
 * Division Weekly Brief: module access, filing (contributor rows), full listing (admin / report viewers), view vs edit on reports.
 */
final class DivisionWeeklyBriefGate
{
    public static function currentRole(): int
    {
        return (int) (user_session('role') ?? user_session('user_role') ?? 0);
    }

    public static function isSystemAdmin(?int $role = null): bool
    {
        $r = $role ?? self::currentRole();

        return $r === 10;
    }

    public static function isListedContributor(int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $staffId)
            ->exists();
    }

    /**
     * Staff IDs configured to see all units’ reports (read-only for others’ drafts); does not include role-10 admins.
     *
     * @return list<int>
     */
    public static function reportViewerStaffIds(): array
    {
        $raw = WeeklyBriefingSetting::current()->report_viewer_staff_ids;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    public static function isListedReportViewer(?int $staffId = null): bool
    {
        $sid = $staffId ?? (int) user_session('staff_id');
        if ($sid <= 0) {
            return false;
        }

        return in_array($sid, self::reportViewerStaffIds(), true);
    }

    public static function canAccessModule(): bool
    {
        if (self::isSystemAdmin()) {
            return true;
        }

        $sid = (int) user_session('staff_id');

        return self::isListedContributor($sid) || self::isListedReportViewer($sid);
    }

    /**
     * Contribution keys this user may start / edit / submit (their contributor rows only).
     *
     * @return list<string>
     */
    public static function contributionKeysForFiling(): array
    {
        if (! self::canAccessModule()) {
            return [];
        }

        $staffId = (int) user_session('staff_id');

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $staffId)
            ->pluck('contribution_key')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keys to include in report lists (all configured units for admin / report viewers; otherwise filing keys only).
     *
     * @return list<string>
     */
    public static function contributionKeysForReportListing(): array
    {
        if (! self::canAccessModule()) {
            return [];
        }

        $settings = WeeklyBriefingSetting::current();
        if (! $settings->contributors()->exists()) {
            return [];
        }

        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return $settings->contributors()
                ->distinct()
                ->pluck('contribution_key')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return self::contributionKeysForFiling();
    }

    public static function mayUseContributionKey(string $contributionKey): bool
    {
        if ($contributionKey === '' || ! self::canAccessModule()) {
            return false;
        }

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', (int) user_session('staff_id'))
            ->where('contribution_key', $contributionKey)
            ->exists();
    }

    public static function mayViewReport(WeeklyBriefingReport $report): bool
    {
        if (! self::canAccessModule()) {
            return false;
        }
        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return true;
        }

        $uid = (int) user_session('staff_id');

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $uid)
            ->where('contribution_key', $report->contribution_key)
            ->exists();
    }

    public static function mayEditReport(WeeklyBriefingReport $report): bool
    {
        if (! self::canAccessModule()) {
            return false;
        }

        $uid = (int) user_session('staff_id');

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $uid)
            ->where('contribution_key', $report->contribution_key)
            ->exists();
    }

    /**
     * Compiled PDF and completion summary (role 10, legacy permissions, or configured report viewers).
     */
    public static function mayAccessCompiledBriefingExports(): bool
    {
        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return true;
        }
        $perms = user_session('permissions', []) ?? [];

        return in_array(87, $perms, true) || in_array(88, $perms, true);
    }
}
