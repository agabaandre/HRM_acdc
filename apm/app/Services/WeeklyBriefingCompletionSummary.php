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

    public static function directorateSortKeyForKey(string $key): string
    {
        if (str_starts_with($key, 'dr-')) {
            $id = (int) substr($key, 3);

            return strtolower((string) (Directorate::query()->find($id)?->name ?? 'z-'.$id));
        }
        if (str_starts_with($key, 'd-')) {
            $id = (int) substr($key, 2);
            $d = Division::query()->find($id);
            if ($d && $d->directorate_id) {
                return strtolower((string) (Directorate::query()->find($d->directorate_id)?->name ?? 'z-'.$d->directorate_id));
            }
        }

        return 'zz';
    }

    /**
     * @return list<array{key: string, label: string, directorate_name: string, status: string, contacts: string, major_happenings: string}>
     */
    public static function rows(WeeklyBriefingSetting $settings, int $isoYear, int $isoWeek): array
    {
        $keys = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values();
        if ($keys->isEmpty()) {
            return [];
        }

        $reports = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $isoYear)
            ->where('report_iso_week', $isoWeek)
            ->whereIn('contribution_key', $keys)
            ->get()
            ->keyBy('contribution_key');

        $rows = [];
        foreach ($keys as $key) {
            $k = (string) $key;
            $report = $reports->get($k);
            $rows[] = [
                'key' => $k,
                'label' => WeeklyBriefingContributor::presentationLabelForContributionKey($k),
                'directorate_name' => self::directorateNameForKey($k),
                'status' => $report ? (string) $report->status : 'missing',
                'contacts' => self::contactNamesForKey($settings, $k),
                'major_happenings' => self::majorHappeningsTitlesFromReport($report),
            ];
        }

        usort($rows, function (array $a, array $b) {
            $da = strtolower($a['directorate_name'].'|'.$a['label']);
            $db = strtolower($b['directorate_name'].'|'.$b['label']);

            return $da <=> $db;
        });

        return $rows;
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

    private static function directorateNameForKey(string $key): string
    {
        if (str_starts_with($key, 'dr-')) {
            $id = (int) substr($key, 3);

            return (string) (Directorate::query()->find($id)?->name ?? '');
        }
        if (str_starts_with($key, 'd-')) {
            $id = (int) substr($key, 2);
            $d = Division::query()->find($id);
            if ($d && $d->directorate_id) {
                return (string) (Directorate::query()->find($d->directorate_id)?->name ?? '');
            }
        }

        return '';
    }

    private static function majorHappeningsTitlesFromReport(?WeeklyBriefingReport $report): string
    {
        if (! $report) {
            return '—';
        }
        $parts = [];
        $n = 1;
        foreach ($report->section1_major_happenings ?? [] as $row) {
            $t = trim((string) ($row['major_happening'] ?? ''));
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
            $dir = self::directorateSortKeyForKey($k);
            $kind = str_starts_with($k, 'dr-') ? '0' : '1';
            $label = strtolower($r->contributionEntityLabel());

            return $dir.'|'.$kind.'|'.$label;
        })->values();
    }
}
