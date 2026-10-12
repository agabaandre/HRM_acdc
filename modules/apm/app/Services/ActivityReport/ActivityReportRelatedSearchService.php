<?php

namespace App\Services\ActivityReport;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use Carbon\Carbon;

class ActivityReportRelatedSearchService
{
    /**
     * Search memos related to the report, focused on the same calendar quarter.
     *
     * @return list<array{value: string, related_source_type: string, related_source_id: int, title: string}>
     */
    public function search(ActivityReport $report, string $q = '', int $limit = 30): array
    {
        $q = trim($q);
        $like = $q !== ''
            ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%'
            : null;

        [$quarterStart, $quarterEnd] = $this->quarterBoundsForReport($report);
        $excludeType = (string) $report->source_type;
        $excludeId = (int) $report->source_id;
        $divisionId = $report->division_id !== null ? (int) $report->division_id : null;

        $results = collect();

        foreach ([
            [Activity::class, 'Activity', 'activity_title', 'date_from', 'date_to'],
            [SpecialMemo::class, 'Special memo', 'activity_title', 'date_from', 'date_to'],
            [NonTravelMemo::class, 'Non-travel', 'activity_title', 'memo_date', 'memo_date'],
        ] as [$class, $label, $titleCol, $startCol, $endCol]) {
            $query = $class::query()
                ->select(['id', $titleCol.' as title', 'document_number', 'division_id'])
                ->whereDate($startCol, '<=', $quarterEnd->toDateString())
                ->whereDate($endCol, '>=', $quarterStart->toDateString());

            if ($like !== null) {
                $query->where(function ($w) use ($like, $titleCol) {
                    $w->where($titleCol, 'like', $like)
                        ->orWhere('document_number', 'like', $like);
                });
            }

            if ($class === $excludeType) {
                $query->where('id', '!=', $excludeId);
            }

            if ($divisionId !== null) {
                $query->orderByRaw('CASE WHEN division_id = ? THEN 0 ELSE 1 END', [$divisionId]);
            }
            $query->orderByDesc('id');

            foreach ($query->limit(20)->get() as $row) {
                if ($class === $excludeType && (int) $row->id === $excludeId) {
                    continue;
                }
                $results->push([
                    'value' => $class.'#'.$row->id,
                    'related_source_type' => $class,
                    'related_source_id' => (int) $row->id,
                    'title' => trim(($row->document_number ? $row->document_number.' — ' : '').($row->title ?? '').' ('.$label.')'),
                    'same_division' => $divisionId !== null && (int) ($row->division_id ?? 0) === $divisionId,
                ]);
            }
        }

        return $results
            ->sortBy([
                ['same_division', 'desc'],
                ['related_source_id', 'desc'],
            ])
            ->take($limit)
            ->map(function (array $row) {
                unset($row['same_division']);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function quarterBoundsForReport(ActivityReport $report): array
    {
        $anchor = $report->start_date
            ?? $report->end_date
            ?? Carbon::now();

        if (! $anchor instanceof Carbon) {
            $anchor = Carbon::parse($anchor);
        }

        $quarter = (int) ceil(((int) $anchor->month) / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($anchor->year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [$start, $end];
    }
}
