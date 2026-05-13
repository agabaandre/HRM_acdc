<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;

class WeeklyBriefingCompletionSummary
{
    public static function labelForKey(string $key): string
    {
        if ($key === '') {
            return '—';
        }
        if (str_starts_with($key, 'dr-')) {
            $id = (int) substr($key, 3);
            $n = Directorate::query()->find($id)?->name;

            return $n !== null && $n !== '' ? 'Directorate: '.$n : 'Directorate #'.$id;
        }
        if (str_starts_with($key, 'd-')) {
            $id = (int) substr($key, 2);
            $d = Division::query()->find($id);

            return $d?->division_name ?? 'Division #'.$id;
        }

        return $key;
    }

    public static function directorateSortKeyForKey(string $key, ?WeeklyBriefingReport $report = null): string
    {
        if (str_starts_with($key, 'dr-')) {
            $id = (int) substr($key, 3);

            return strtolower((string) (Directorate::query()->find($id)?->name ?? 'z-'.$id));
        }
        if (str_starts_with($key, 'd-')) {
            $id = (int) substr($key, 2);
            $d = Division::query()->find($id);
            $dirId = null;
            if ($d && $d->directorate_id) {
                $dirId = (int) $d->directorate_id;
            } elseif ($report && (int) ($report->directorate_id ?? 0) > 0) {
                $dirId = (int) $report->directorate_id;
            }
            if ($dirId !== null && $dirId > 0) {
                return strtolower((string) (Directorate::query()->find($dirId)?->name ?? 'z-'.$dirId));
            }
        }

        return 'zz';
    }

    /**
     * @return list<array{key: string, label: string, directorate_name: string, status: string, contacts: string, major_happenings: string, director_review: string, director_trail: string}>
     */
    public static function rows(WeeklyBriefingSetting $settings, int $isoYear, int $isoWeek): array
    {
        $keys = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values()->all();

        return self::rowsForContributionKeys($settings, $isoYear, $isoWeek, $keys);
    }

    /**
     * Same shape as {@see rows}, but only for the given contribution keys (e.g. director-scoped subset).
     *
     * @param  list<string>|\Illuminate\Support\Collection<int, string>  $contributionKeys
     * @return list<array{key: string, label: string, directorate_name: string, status: string, contacts: string, major_happenings: string, director_review: string, director_trail: string}>
     */
    public static function rowsForContributionKeys(WeeklyBriefingSetting $settings, int $isoYear, int $isoWeek, array|Collection $contributionKeys): array
    {
        $keys = collect($contributionKeys)
            ->map(fn ($k) => trim((string) $k))
            ->filter(fn ($k) => $k !== '')
            ->unique()
            ->values()
            ->all();

        if ($keys === []) {
            return [];
        }

        $reports = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $isoYear)
            ->where('report_iso_week', $isoWeek)
            ->whereIn('contribution_key', $keys)
            ->get()
            ->keyBy('contribution_key');

        $rows = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $report = $reports->get($k);
            $rows[] = [
                'key' => $k,
                'label' => WeeklyBriefingContributor::presentationLabelForContributionKey($k),
                'directorate_name' => self::directorateLabelForCompletionRow($k, $report),
                'status' => $report ? (string) $report->status : 'missing',
                'contacts' => self::contactNamesForKey($settings, $k),
                'major_happenings' => self::majorHappeningsTitlesFromReport($report),
                'director_review' => self::directorReviewCell($k, $report),
                'director_trail' => self::directorTrailCell($report),
            ];
        }

        usort($rows, function (array $a, array $b) {
            $da = strtolower($a['directorate_name'].'|'.$a['label']);
            $db = strtolower($b['directorate_name'].'|'.$b['label']);

            return $da <=> $db;
        });

        return $rows;
    }

    private static function directorReviewCell(string $contributionKey, ?WeeklyBriefingReport $report): string
    {
        if (! str_starts_with($contributionKey, 'd-')) {
            return '—';
        }
        $divId = (int) substr($contributionKey, 2);
        $div = Division::query()->find($divId);
        if (! $div || (int) ($div->director_id ?? 0) <= 0) {
            return 'N/A';
        }
        if (! $report) {
            return '—';
        }

        return $report->directorReviewSummaryLine();
    }

    private static function directorTrailCell(?WeeklyBriefingReport $report): string
    {
        if (! $report || ! $report->requiresDirectorReview()) {
            return '—';
        }

        return $report->directorReviewTrailSummary();
    }

    /**
     * Directorate display name from the system `directorates` table when the row is linked
     * (dr-* key, or division / weekly briefing report directorate_id).
     */
    private static function directorateLabelForCompletionRow(string $key, ?WeeklyBriefingReport $report): string
    {
        if (str_starts_with($key, 'dr-')) {
            $id = (int) substr($key, 3);
            $name = Directorate::query()->find($id)?->name;
            if ($name !== null && trim((string) $name) !== '') {
                return trim((string) $name);
            }

            return $id > 0 ? 'Directorate #'.$id : '—';
        }
        if (str_starts_with($key, 'd-')) {
            $divId = (int) substr($key, 2);
            $div = Division::query()->find($divId);
            $dirId = null;
            if ($div && (int) ($div->directorate_id ?? 0) > 0) {
                $dirId = (int) $div->directorate_id;
            } elseif ($report && (int) ($report->directorate_id ?? 0) > 0) {
                $dirId = (int) $report->directorate_id;
            }
            if ($dirId !== null && $dirId > 0) {
                $name = Directorate::query()->find($dirId)?->name;
                if ($name !== null && trim((string) $name) !== '') {
                    return trim((string) $name);
                }

                return 'Directorate #'.$dirId;
            }

            return '—';
        }

        return '';
    }

    public static function contactNamesForKey(WeeklyBriefingSetting $settings, string $key): string
    {
        return $settings->contributors()
            ->where('contribution_key', $key)
            ->with('staff')
            ->get()
            ->map(function ($c) {
                $s = $c->staff;
                if (! $s) {
                    return '';
                }
                $n = trim((string) ($s->name ?? ''));
                if ($n !== '') {
                    return $n;
                }

                return trim(implode(' ', array_filter([$s->fname ?? '', $s->lname ?? ''])));
            })
            ->filter()
            ->unique()
            ->join(', ');
    }

    private static function majorHappeningsTitlesFromReport(?WeeklyBriefingReport $report): string
    {
        if (! $report) {
            return '—';
        }
        $parts = [];
        $n = 1;
        foreach ($report->section1_major_happenings ?? [] as $row) {
            $t = trim(strip_tags((string) ($row['major_happening'] ?? '')));
            if ($t === '') {
                continue;
            }
            $parts[] = $n.'. '.$t;
            $n++;
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /**
     * @param  Collection<int, WeeklyBriefingReport>  $reports
     * @return Collection<int, WeeklyBriefingReport>
     */
    public static function sortReportsForCompiled(Collection $reports): Collection
    {
        return $reports->sortBy(function (WeeklyBriefingReport $r) {
            $k = (string) ($r->contribution_key ?? '');
            $dir = self::directorateSortKeyForKey($k, $r);
            $kind = str_starts_with($k, 'dr-') ? '0' : '1';
            $label = strtolower($r->contributionEntityLabel());

            return $dir.'|'.$kind.'|'.$label;
        })->values();
    }
}
