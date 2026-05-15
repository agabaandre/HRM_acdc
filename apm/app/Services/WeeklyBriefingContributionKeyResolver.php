<?php

namespace App\Services;

use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Each configured contributor row maps to one division-scoped brief ({@code d-{division_id}}).
 * Directorate grouping is for display and PDF compilation only, not shared report rows.
 */
final class WeeklyBriefingContributionKeyResolver
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function keyFromSettingsRow(array $row): string
    {
        $divisionId = self::contributingDivisionIdFromRow($row);
        if ($divisionId <= 0) {
            throw new \InvalidArgumentException('Each contributor row needs a contributing division.');
        }

        return WeeklyBriefingContributor::contributionKeyForDivision($divisionId);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function contributingDivisionIdFromRow(array $row): int
    {
        $divId = (int) ($row['contribution_division_id'] ?? 0);
        if ($divId > 0) {
            return $divId;
        }

        return (int) ($row['apm_division_id'] ?? 0);
    }

    /**
     * Key used on the hub and for report storage (migrates legacy {@code dr-*} rows).
     */
    public static function effectiveKeyForContributor(WeeklyBriefingContributor $contributor): string
    {
        $stored = trim((string) ($contributor->contribution_key ?? ''));
        if (str_starts_with($stored, 'd-')) {
            return $stored;
        }

        if (str_starts_with($stored, 'dr-')) {
            $apmDiv = (int) ($contributor->apm_division_id ?? 0);
            if ($apmDiv > 0) {
                return WeeklyBriefingContributor::contributionKeyForDivision($apmDiv);
            }
        }

        return $stored;
    }

    /**
     * Split legacy {@code dr-{directorate}} reports onto division keys after settings save.
     *
     * @param  list<array{staff_id: int, apm_division_id: int, contribution_key: string, display_name: ?string}>  $normalized
     */
    public static function migrateLegacyDirectorateReportsForNormalizedRows(array $normalized): void
    {
        $claimedDrReportIds = [];

        foreach ($normalized as $row) {
            $toKey = (string) ($row['contribution_key'] ?? '');
            if (! str_starts_with($toKey, 'd-')) {
                continue;
            }

            $divisionId = (int) substr($toKey, 2);
            $division = Division::query()->find($divisionId);
            if (! $division) {
                continue;
            }

            $dirId = DirectorateDivisionLink::resolveDirectorateIdForDivision($division);
            if ($dirId <= 0) {
                continue;
            }

            $fromKey = WeeklyBriefingContributor::contributionKeyForDirectorate($dirId);
            self::claimOneLegacyDirectorateReport($fromKey, $toKey, $division, $claimedDrReportIds);
        }
    }

    /**
     * @param  array<int, true>  $claimedDrReportIds
     */
    private static function claimOneLegacyDirectorateReport(
        string $fromKey,
        string $toKey,
        Division $division,
        array &$claimedDrReportIds,
    ): void {
        $legacyReports = WeeklyBriefingReport::query()
            ->where('contribution_key', $fromKey)
            ->orderBy('id')
            ->get();

        foreach ($legacyReports as $legacy) {
            if (isset($claimedDrReportIds[$legacy->id])) {
                continue;
            }

            $targetExists = WeeklyBriefingReport::query()
                ->where('contribution_key', $toKey)
                ->where('report_iso_week_year', $legacy->report_iso_week_year)
                ->where('report_iso_week', $legacy->report_iso_week)
                ->exists();

            if ($targetExists) {
                continue;
            }

            $legacy->update([
                'contribution_key' => $toKey,
                'division_id' => $division->id,
                'directorate_id' => $division->directorate_id,
            ]);
            $claimedDrReportIds[$legacy->id] = true;

            return;
        }
    }

    /**
     * @param  Collection<int, WeeklyBriefingContributor>  $contributors
     * @return list<string>
     */
    public static function effectiveKeysForContributors(Collection $contributors): array
    {
        return $contributors
            ->map(fn (WeeklyBriefingContributor $c) => self::effectiveKeyForContributor($c))
            ->filter(fn (string $k) => $k !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the filing-week report for a hub row (handles legacy {@code dr-*} rows via {@see division_id}).
     *
     * @param  Collection<string, WeeklyBriefingReport>  $reportsByContributionKey
     */
    public static function resolveReportForContributor(
        WeeklyBriefingContributor $contributor,
        Collection $reportsByContributionKey,
        int $isoYear,
        int $isoWeek,
    ): ?WeeklyBriefingReport {
        $effectiveKey = self::effectiveKeyForContributor($contributor);
        if ($effectiveKey !== '') {
            $byKey = $reportsByContributionKey->get($effectiveKey);
            if ($byKey instanceof WeeklyBriefingReport) {
                return $byKey;
            }
        }

        $stored = trim((string) ($contributor->contribution_key ?? ''));
        if ($stored !== '' && $stored !== $effectiveKey) {
            $legacy = $reportsByContributionKey->get($stored);
            if ($legacy instanceof WeeklyBriefingReport && self::reportMatchesContributorDivision($legacy, $contributor)) {
                return $legacy;
            }
        }

        $divisionId = self::divisionIdForContributor($contributor);
        if ($divisionId <= 0) {
            return null;
        }

        foreach ($reportsByContributionKey as $candidate) {
            if (! $candidate instanceof WeeklyBriefingReport) {
                continue;
            }
            if ((int) $candidate->report_iso_week_year !== $isoYear || (int) $candidate->report_iso_week !== $isoWeek) {
                continue;
            }
            if ((int) $candidate->division_id === $divisionId) {
                return $candidate;
            }
        }

        return null;
    }

    public static function divisionIdForContributor(WeeklyBriefingContributor $contributor): int
    {
        $effectiveKey = self::effectiveKeyForContributor($contributor);
        if (str_starts_with($effectiveKey, 'd-')) {
            return (int) substr($effectiveKey, 2);
        }

        return (int) ($contributor->apm_division_id ?? 0);
    }

    public static function reportMatchesContributorDivision(WeeklyBriefingReport $report, WeeklyBriefingContributor $contributor): bool
    {
        $divisionId = self::divisionIdForContributor($contributor);

        return $divisionId > 0 && (int) $report->division_id === $divisionId;
    }

    public static function contributorOwnsReport(WeeklyBriefingContributor $contributor, WeeklyBriefingReport $report): bool
    {
        $reportKey = trim((string) ($report->contribution_key ?? ''));
        if ($reportKey === '') {
            return false;
        }

        $effectiveKey = self::effectiveKeyForContributor($contributor);
        if ($effectiveKey !== '' && $reportKey === $effectiveKey) {
            return true;
        }

        $stored = trim((string) ($contributor->contribution_key ?? ''));
        if ($stored !== '' && $reportKey === $stored) {
            return self::reportMatchesContributorDivision($report, $contributor);
        }

        return self::reportMatchesContributorDivision($report, $contributor);
    }

    public static function reportMatchesConfiguredContributor(WeeklyBriefingReport $report): bool
    {
        foreach (WeeklyBriefingSetting::current()->contributors()->get() as $contributor) {
            if (self::contributorOwnsReport($contributor, $report)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index reports by contribution_key and alias each row under {@code d-{division_id}} when present.
     *
     * @param  Collection<int, WeeklyBriefingReport>  $reports
     * @return Collection<string, WeeklyBriefingReport>
     */
    public static function reportsIndexedWithDivisionAliases(Collection $reports): Collection
    {
        /** @var Collection<string, WeeklyBriefingReport> $indexed */
        $indexed = $reports->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);

        foreach ($reports as $report) {
            $divisionId = (int) $report->division_id;
            if ($divisionId <= 0) {
                continue;
            }
            $divisionKey = WeeklyBriefingContributor::contributionKeyForDivision($divisionId);
            if (! $indexed->has($divisionKey)) {
                $indexed->put($divisionKey, $report);
            }
        }

        return $indexed;
    }

    /**
     * Re-key legacy {@code dr-*} reports using each row's {@code division_id} (e.g. division 8 → {@code d-8}).
     */
    public static function migrateLegacyReportsByDivisionId(): int
    {
        $migrated = 0;

        $legacyReports = WeeklyBriefingReport::query()
            ->where('contribution_key', 'like', 'dr-%')
            ->orderBy('id')
            ->get();

        foreach ($legacyReports as $report) {
            $divisionId = (int) $report->division_id;
            if ($divisionId <= 0) {
                continue;
            }

            $toKey = WeeklyBriefingContributor::contributionKeyForDivision($divisionId);
            if ((string) $report->contribution_key === $toKey) {
                continue;
            }

            $exists = WeeklyBriefingReport::query()
                ->where('contribution_key', $toKey)
                ->where('report_iso_week_year', $report->report_iso_week_year)
                ->where('report_iso_week', $report->report_iso_week)
                ->where('id', '!=', $report->id)
                ->exists();

            if ($exists) {
                Log::warning('weekly-briefing: skipped dr→d migration (target week already has a report)', [
                    'report_id' => $report->id,
                    'from' => $report->contribution_key,
                    'to' => $toKey,
                    'iso_year' => $report->report_iso_week_year,
                    'iso_week' => $report->report_iso_week,
                ]);

                continue;
            }

            $division = Division::query()->find($divisionId);
            $report->update([
                'contribution_key' => $toKey,
                'division_id' => $divisionId,
                'directorate_id' => $division?->directorate_id,
            ]);
            $migrated++;
        }

        return $migrated;
    }

    /**
     * Persist division keys on contributor rows and split any shared {@code dr-*} reports (no settings form required).
     */
    public static function repairContributorKeysInDatabase(): int
    {
        $reportsMigrated = self::migrateLegacyReportsByDivisionId();

        $settings = WeeklyBriefingSetting::current();
        $rows = $settings->contributors()->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return $reportsMigrated;
        }

        $normalized = [];
        foreach ($rows as $contributor) {
            $targetKey = self::effectiveKeyForContributor($contributor);
            if ($targetKey === '' || ! str_starts_with($targetKey, 'd-')) {
                continue;
            }
            $normalized[] = [
                'staff_id' => (int) $contributor->staff_id,
                'apm_division_id' => (int) $contributor->apm_division_id,
                'contribution_key' => $targetKey,
                'display_name' => $contributor->display_name,
            ];
        }

        if ($normalized !== []) {
            self::migrateLegacyDirectorateReportsForNormalizedRows($normalized);
        }

        $contributorsUpdated = 0;
        foreach ($rows as $contributor) {
            $targetKey = self::effectiveKeyForContributor($contributor);
            $stored = trim((string) ($contributor->contribution_key ?? ''));
            if ($targetKey === '' || $stored === $targetKey) {
                continue;
            }
            $contributor->update(['contribution_key' => $targetKey]);
            $contributorsUpdated++;
        }

        return $reportsMigrated + $contributorsUpdated;
    }
}
