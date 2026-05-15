<?php

namespace App\Services;

use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;

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

            $dirId = (int) ($division->directorate_id ?? 0);
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
     * Persist division keys on contributor rows and split any shared {@code dr-*} reports (no settings form required).
     */
    public static function repairContributorKeysInDatabase(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $rows = $settings->contributors()->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return 0;
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

        $updated = 0;
        foreach ($rows as $contributor) {
            $targetKey = self::effectiveKeyForContributor($contributor);
            $stored = trim((string) ($contributor->contribution_key ?? ''));
            if ($targetKey === '' || $stored === $targetKey) {
                continue;
            }
            $contributor->update(['contribution_key' => $targetKey]);
            $updated++;
        }

        return $updated;
    }
}
