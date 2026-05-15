<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

        if (self::divisionIdsForStaffActingAsDirector($sid) !== []) {
            return true;
        }

        if (! self::directoratesTableHasDirectorIdColumn()) {
            return false;
        }

        $q = Directorate::query()->where('director_id', $sid);
        if (Schema::hasColumn('directorates', 'is_active')) {
            $q->where('is_active', true);
        }

        return $q->exists();
    }

    public static function directoratesTableHasDirectorIdColumn(): bool
    {
        return Schema::hasTable('directorates') && Schema::hasColumn('directorates', 'director_id');
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
     * @return list<int>
     */
    public static function directorateIdsForStaffDirector(?int $staffId = null): array
    {
        $sid = $staffId ?? self::sessionStaffId();
        if ($sid <= 0 || ! self::directoratesTableHasDirectorIdColumn()) {
            return [];
        }

        $q = Directorate::query()->where('director_id', $sid);
        if (Schema::hasColumn('directorates', 'is_active')) {
            $q->where('is_active', true);
        }

        return $q->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function divisionIdsForStaffActingAsDirector(?int $staffId = null): array
    {
        $sid = $staffId ?? self::sessionStaffId();
        if ($sid <= 0) {
            return [];
        }

        return Division::queryForStaffActingAsDirector($sid)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function divisionIdsUnderDirectorOversight(?int $staffId = null): array
    {
        $sid = $staffId ?? self::sessionStaffId();
        $divisionIds = self::divisionIdsForStaffActingAsDirector($sid);
        $directorateIds = self::directorateIdsForStaffDirector($sid);
        if ($directorateIds !== []) {
            $fromDirectorates = Division::query()
                ->whereIn('directorate_id', $directorateIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $divisionIds = array_merge($divisionIds, $fromDirectorates);

            $directorStaffIds = Directorate::query()
                ->whereIn('id', $directorateIds)
                ->pluck('director_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            if ($directorStaffIds !== []) {
                $fromSharedDirector = Division::query()
                    ->whereIn('director_id', $directorStaffIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $divisionIds = array_merge($divisionIds, $fromSharedDirector);
            }
        }

        return array_values(array_unique(array_filter($divisionIds, fn (int $id) => $id > 0)));
    }

    public static function contributorRowVisibleToDirector(WeeklyBriefingContributor $contributor, ?int $staffId = null): bool
    {
        $divId = WeeklyBriefingContributionKeyResolver::divisionIdForContributor($contributor);
        if ($divId <= 0) {
            $stored = trim((string) ($contributor->contribution_key ?? ''));
            if (str_starts_with($stored, 'dr-')) {
                $dirId = (int) substr($stored, 3);

                return in_array($dirId, self::directorateIdsForStaffDirector($staffId), true);
            }

            return false;
        }

        if (in_array($divId, self::divisionIdsUnderDirectorOversight($staffId), true)) {
            return true;
        }

        $directorateIds = self::directorateIdsForStaffDirector($staffId);
        if ($directorateIds === []) {
            return false;
        }

        $stored = trim((string) ($contributor->contribution_key ?? ''));
        if (str_starts_with($stored, 'dr-')) {
            $dirId = (int) substr($stored, 3);

            return in_array($dirId, $directorateIds, true);
        }

        return WeeklyBriefingReport::query()
            ->where('division_id', $divId)
            ->whereIn('directorate_id', $directorateIds)
            ->exists();
    }

    /**
     * @param  list<int>  $directorateIds
     */
    public static function contributorRowUnderDirectorates(WeeklyBriefingContributor $contributor, array $directorateIds): bool
    {
        if ($directorateIds === []) {
            return false;
        }
        $divId = WeeklyBriefingContributionKeyResolver::divisionIdForContributor($contributor);
        if ($divId <= 0) {
            return false;
        }
        $div = Division::query()->find($divId);
        if (! $div) {
            return false;
        }

        foreach ($directorateIds as $dirId) {
            if (DirectorateDivisionLink::divisionBelongsToDirectorate($div, (int) $dirId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Contribution keys for configured units in directorates this user directs.
     *
     * @return list<string>
     */
    public static function directorManagedContributionKeysForListing(): array
    {
        if (! self::mayActAsDivisionDirector()) {
            return [];
        }
        if (self::divisionIdsUnderDirectorOversight() === []) {
            return [];
        }

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->get()
            ->filter(fn (WeeklyBriefingContributor $c) => self::contributorRowVisibleToDirector($c))
            ->map(fn (WeeklyBriefingContributor $c) => WeeklyBriefingContributionKeyResolver::effectiveKeyForContributor($c))
            ->filter(fn (string $k) => $k !== '')
            ->unique()
            ->values()
            ->all();
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
        $sid = self::sessionStaffId();
        $divId = (int) ($report->division_id ?? 0);
        if ($divId <= 0 && str_starts_with((string) ($report->contribution_key ?? ''), 'd-')) {
            $divId = (int) substr((string) $report->contribution_key, 2);
        }
        if ($divId > 0) {
            $div = Division::query()->find($divId);
            if ($div && $div->staffActsAsDivisionDirector($sid)) {
                return true;
            }
        }

        if (! self::directoratesTableHasDirectorIdColumn()) {
            return false;
        }

        $dir = $report->directorateForDirectorReview();
        if (! $dir) {
            return false;
        }

        return (int) ($dir->director_id ?? 0) === $sid;
    }

    /**
     * Directors may open submitted briefs for review; may open drafts/locked for status visibility (read-only on edit screen).
     */
    public static function mayDirectorAccessReportOnHub(WeeklyBriefingReport $report): bool
    {
        return self::mayViewAsDivisionDirector($report);
    }

    public static function mayDirectorReviewReportOnHub(WeeklyBriefingReport $report): bool
    {
        return self::mayDirectorAccessReportOnHub($report)
            && $report->status === WeeklyBriefingReport::STATUS_SUBMITTED
            && $report->requiresDirectorReview();
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

        if (self::mayViewAllConfiguredReportsOnHub()) {
            return WeeklyBriefingContributionKeyResolver::effectiveKeysForContributors(
                $settings->contributors()->get()
            );
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

        if (self::mayViewAllConfiguredReportsOnHub()) {
            return $q->get();
        }

        $sid = self::sessionStaffId();

        return $q->get()->filter(function (WeeklyBriefingContributor $c) use ($sid): bool {
            if ((int) ($c->staff_id ?? 0) === $sid) {
                return true;
            }

            return self::contributorRowVisibleToDirector($c, $sid);
        })->values();
    }

    public static function isDirectorateDirector(): bool
    {
        return self::mayActAsDivisionDirector();
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

    /**
     * System admin (role 10) or staff in {@see reportViewerStaffIds}: see every configured unit on the hub with view/PDF actions.
     */
    public static function mayViewAllConfiguredReportsOnHub(): bool
    {
        return self::isSystemAdmin() || self::isListedReportViewer();
    }

    public static function mayViewReport(WeeklyBriefingReport $report): bool
    {
        if (! self::canAccessModule()) {
            return false;
        }
        if (self::mayViewAllConfiguredReportsOnHub()) {
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
        if (self::mayViewAllConfiguredReportsOnHub()) {
            return true;
        }
        $perms = user_session('permissions', []) ?? [];

        return in_array(87, $perms, true) || in_array(88, $perms, true);
    }
}
