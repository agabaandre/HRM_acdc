<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;

/**
 * Weekly brief: module access, filing (contributor rows), full listing (admin / report viewers), view vs edit on reports.
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
        $sid = $staffId ?? self::sessionStaffId();
        if ($sid <= 0) {
            return false;
        }

        return in_array($sid, self::reportViewerStaffIds(), true);
    }

    /**
     * Logged-in staff id from session (some payloads use auth_staff_id only).
     */
    public static function sessionStaffId(): int
    {
        foreach (['staff_id', 'auth_staff_id'] as $key) {
            $v = user_session($key);
            if ($v !== null && $v !== '' && (int) $v > 0) {
                return (int) $v;
            }
        }

        return 0;
    }

    /**
     * True when the user is assigned as director on at least one active directorate (`directorates.director_id`).
     * (Method name kept for backwards compatibility with callers.)
     */
    public static function mayActAsDivisionDirector(?int $staffId = null): bool
    {
        if (! self::divisionDirectorsModuleAccessEnabled()) {
            return false;
        }
        $sid = $staffId ?? self::sessionStaffId();
        if ($sid <= 0) {
            return false;
        }

        return Directorate::query()
            ->where('is_active', true)
            ->where('director_id', $sid)
            ->exists();
    }

    /**
     * Weekly brief settings: when false, directorate directors do not get module/nav (unless contributor/viewer).
     */
    private static function divisionDirectorsModuleAccessEnabled(): bool
    {
        $v = WeeklyBriefingSetting::current()->division_directors_can_access_module;
        if ($v === null) {
            return true;
        }

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    public static function canAccessModule(): bool
    {
        if (self::isSystemAdmin()) {
            return true;
        }

        $sid = self::sessionStaffId();

        return self::isListedContributor($sid) || self::isListedReportViewer($sid) || self::mayActAsDivisionDirector($sid);
    }

    /**
     * Contribution keys this user may manage as **directorate director** ({@see Directorate::director_id}),
     * intersected with configured contributor keys (includes legacy d-* rows mapped via division.directorate_id).
     *
     * @return list<string>
     */
    public static function directorManagedContributionKeysForListing(): array
    {
        if (! self::mayActAsDivisionDirector()) {
            return [];
        }
        $settings = WeeklyBriefingSetting::current();
        $configured = $settings->contributors()
            ->get()
            ->map(fn (WeeklyBriefingContributor $c) => WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($c))
            ->filter(fn (string $k) => $k !== '')
            ->unique()
            ->values()
            ->all();
        if ($configured === []) {
            return [];
        }
        $configuredSet = array_fill_keys($configured, true);
        $sid = self::sessionStaffId();
        $out = [];
        foreach (array_keys($configuredSet) as $k) {
            if (str_starts_with($k, 'dr-')) {
                $id = (int) substr($k, 3);
                $dir = Directorate::query()->find($id);
                if ($dir && (int) ($dir->director_id ?? 0) === $sid) {
                    $out[] = $k;
                }
            } elseif (str_starts_with($k, 'd-')) {
                $divId = (int) substr($k, 2);
                $div = Division::query()->find($divId);
                $dirId = (int) ($div?->directorate_id ?? 0);
                if ($dirId <= 0) {
                    continue;
                }
                $dir = Directorate::query()->find($dirId);
                if ($dir && (int) ($dir->director_id ?? 0) === $sid) {
                    $out[] = $k;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function mayViewAsDivisionDirector(WeeklyBriefingReport $report): bool
    {
        if (! self::mayActAsDivisionDirector()) {
            return false;
        }
        if (! self::reportHasConfiguredContributionKey($report)) {
            return false;
        }

        return self::currentUserIsDirectorForDivisionReport($report);
    }

    public static function mayEditAsDivisionDirector(WeeklyBriefingReport $report): bool
    {
        return self::mayViewAsDivisionDirector($report);
    }

    public static function mayMarkDirectorReview(WeeklyBriefingReport $report): bool
    {
        return self::mayEditAsDivisionDirector($report)
            && $report->status === WeeklyBriefingReport::STATUS_SUBMITTED
            && $report->requiresDirectorReview();
    }

    private static function reportHasConfiguredContributionKey(WeeklyBriefingReport $report): bool
    {
        return WeeklyBriefingContributionKeyResolver::reportMatchesConfiguredContributor($report);
    }

    private static function currentUserIsDirectorForDivisionReport(WeeklyBriefingReport $report): bool
    {
        $dir = $report->directorateForDirectorReview();
        if (! $dir) {
            return false;
        }
        $uid = self::sessionStaffId();

        return (int) ($dir->director_id ?? 0) === $uid;
    }

    public static function mayDownloadDirectorateCombinedPdf(int $isoYear, int $isoWeek, int $directorateId): bool
    {
        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return false;
        }

        $sid = self::sessionStaffId();
        if ($sid <= 0 || ! self::mayActAsDivisionDirector($sid)) {
            return false;
        }

        $coll = WeeklyBriefingDirectorateCombined::submittedReportsForDirectorDirectorate(
            $sid,
            $directorateId,
            $isoYear,
            $isoWeek,
            null
        );

        return $coll->isNotEmpty();
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

        $staffId = self::sessionStaffId();

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $staffId)
            ->get()
            ->map(fn (WeeklyBriefingContributor $c) => WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($c))
            ->filter(fn (string $k) => $k !== '')
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

        $filing = self::contributionKeysForFiling();
        $directorKeys = self::directorManagedContributionKeysForListing();

        return array_values(array_unique(array_merge($filing, $directorKeys)));
    }

    /**
     * Contributor rows visible on the Weekly brief hub (one row per configured contributor, not de-duplicated by key).
     *
     * @return Collection<int, WeeklyBriefingContributor>
     */
    public static function contributorsForReportListing(): Collection
    {
        if (! self::canAccessModule()) {
            return collect();
        }

        $settings = WeeklyBriefingSetting::current();
        if (! $settings->contributors()->exists()) {
            return collect();
        }

        $q = $settings->contributors()->with(['staff', 'apmDivision'])->orderBy('id');

        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return $q->get();
        }

        $sid = self::sessionStaffId();
        $directorKeys = array_fill_keys(self::directorManagedContributionKeysForListing(), true);

        return $q->get()->filter(function (WeeklyBriefingContributor $c) use ($sid, $directorKeys): bool {
            if ((int) ($c->staff_id ?? 0) === $sid) {
                return true;
            }
            $k = WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($c);

            return $k !== '' && isset($directorKeys[$k]);
        })->values();
    }

    public static function mayUseContributionKey(string $contributionKey): bool
    {
        if ($contributionKey === '' || ! self::canAccessModule()) {
            return false;
        }

        $uid = self::sessionStaffId();

        foreach (WeeklyBriefingSetting::current()->contributors()->where('staff_id', $uid)->get() as $contributor) {
            $stored = trim((string) ($contributor->contribution_key ?? ''));
            if ($stored === $contributionKey || WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($contributor) === $contributionKey) {
                return true;
            }
        }

        return false;
    }

    public static function contributorRowForEffectiveKey(int $staffId, string $contributionKey): ?WeeklyBriefingContributor
    {
        foreach (WeeklyBriefingSetting::current()->contributors()->where('staff_id', $staffId)->get() as $contributor) {
            $stored = trim((string) ($contributor->contribution_key ?? ''));
            if ($stored === $contributionKey || WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($contributor) === $contributionKey) {
                return $contributor;
            }
        }

        return null;
    }

    private static function userIsContributorForReport(int $staffId, WeeklyBriefingReport $report): bool
    {
        foreach (WeeklyBriefingSetting::current()->contributors()->where('staff_id', $staffId)->get() as $contributor) {
            if (WeeklyBriefingContributionKeyResolver::contributorOwnsReport($contributor, $report)) {
                return true;
            }
        }

        return false;
    }

    public static function mayViewReport(WeeklyBriefingReport $report): bool
    {
        if (! self::canAccessModule()) {
            return false;
        }
        if (self::isSystemAdmin() || self::isListedReportViewer()) {
            return true;
        }

        $uid = self::sessionStaffId();

        return self::userIsContributorForReport($uid, $report)
            || self::mayViewAsDivisionDirector($report);
    }

    public static function mayEditReport(WeeklyBriefingReport $report): bool
    {
        if (! self::canAccessModule()) {
            return false;
        }

        return self::userIsContributorForReport(self::sessionStaffId(), $report);
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
