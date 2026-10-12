<?php

namespace App\Services\ActivityReport;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Models\ReportingRegistryStatus;
use App\Models\SpecialMemo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ActivityReportRegistryService
{
    /** Extramural (2) and external (3) fund types — external source funded. */
    public const EXTERNAL_FUND_TYPE_IDS = [2, 3];

    /**
     * @return array{start: Carbon, end: Carbon, year: int, quarter: int}
     */
    public function currentQuarterBounds(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $quarter = (int) ceil(((int) $now->month) / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($now->year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'year' => (int) $now->year,
            'quarter' => $quarter,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCurrentQuarter(?int $divisionId = null): array
    {
        $bounds = $this->currentQuarterBounds();
        $rows = collect()
            ->merge($this->activityRows($bounds, $divisionId))
            ->merge($this->specialMemoRows($bounds, $divisionId))
            ->merge($this->nonTravelRows($bounds, $divisionId))
            ->sortByDesc(fn ($r) => $r['end_date'] ?? $r['start_date'] ?? '')
            ->values();

        return $this->enrichWithStatuses($rows)->all();
    }

    public function markStarted(string $sourceType, int $sourceId, int $staffId): ReportingRegistryStatus
    {
        $row = ReportingRegistryStatus::query()->firstOrNew([
            'source_id' => $sourceId,
            'source_type' => $sourceType,
        ]);

        if ($row->started_at === null) {
            $row->started_at = now();
            $row->started_by = $staffId;
        }
        $row->save();

        return $row->fresh();
    }

    public function markProcessed(string $sourceType, int $sourceId, int $staffId): ReportingRegistryStatus
    {
        $row = $this->markStarted($sourceType, $sourceId, $staffId);
        if ($row->processed_at === null) {
            $row->processed_at = now();
            $row->processed_by = $staffId;
            $row->save();
        }

        return $row->fresh();
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $bounds
     * @return Collection<int, array<string, mixed>>
     */
    private function activityRows(array $bounds, ?int $divisionId): Collection
    {
        $q = Activity::query()
            ->select([
                'id', 'activity_title', 'document_number', 'division_id', 'fund_type_id',
                'date_from', 'date_to', 'is_single_memo', 'staff_id', 'responsible_person_id',
            ])
            ->where(function ($w) use ($bounds) {
                $w->whereDate('date_from', '<=', $bounds['end']->toDateString())
                    ->whereDate('date_to', '>=', $bounds['start']->toDateString());
            });

        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }

        return $q->get()->map(fn (Activity $a) => [
            'source_id' => (int) $a->id,
            'source_type' => Activity::class,
            'type_label' => ((int) ($a->is_single_memo ?? 0) === 1) ? 'Single memo' : 'Activity',
            'title' => (string) ($a->activity_title ?? ''),
            'document_number' => $a->document_number,
            'division_id' => $a->division_id,
            'fund_type_id' => $a->fund_type_id !== null ? (int) $a->fund_type_id : null,
            'is_external_funded' => in_array((int) ($a->fund_type_id ?? 0), self::EXTERNAL_FUND_TYPE_IDS, true),
            'start_date' => optional($a->date_from)->toDateString(),
            'end_date' => optional($a->date_to)->toDateString(),
        ]);
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $bounds
     * @return Collection<int, array<string, mixed>>
     */
    private function specialMemoRows(array $bounds, ?int $divisionId): Collection
    {
        $q = SpecialMemo::query()
            ->select([
                'id', 'activity_title', 'document_number', 'division_id', 'fund_type_id',
                'date_from', 'date_to', 'staff_id', 'responsible_person_id',
            ])
            ->where(function ($w) use ($bounds) {
                $w->whereDate('date_from', '<=', $bounds['end']->toDateString())
                    ->whereDate('date_to', '>=', $bounds['start']->toDateString());
            });

        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }

        return $q->get()->map(fn (SpecialMemo $m) => [
            'source_id' => (int) $m->id,
            'source_type' => SpecialMemo::class,
            'type_label' => 'Special memo',
            'title' => (string) ($m->activity_title ?? ''),
            'document_number' => $m->document_number,
            'division_id' => $m->division_id,
            'fund_type_id' => $m->fund_type_id !== null ? (int) $m->fund_type_id : null,
            'is_external_funded' => in_array((int) ($m->fund_type_id ?? 0), self::EXTERNAL_FUND_TYPE_IDS, true),
            'start_date' => optional($m->date_from)->toDateString(),
            'end_date' => optional($m->date_to)->toDateString(),
        ]);
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $bounds
     * @return Collection<int, array<string, mixed>>
     */
    private function nonTravelRows(array $bounds, ?int $divisionId): Collection
    {
        $externalIds = self::EXTERNAL_FUND_TYPE_IDS;

        $q = NonTravelMemo::query()
            ->select([
                'id', 'activity_title', 'document_number', 'division_id', 'fund_type_id',
                'memo_date', 'staff_id', 'pra_activity_id',
            ])
            ->where(function ($w) use ($externalIds) {
                $w->where(function ($p) {
                    $p->whereNotNull('pra_activity_id')->where('pra_activity_id', '>', 0);
                })->orWhereIn('fund_type_id', $externalIds);
            })
            ->whereDate('memo_date', '>=', $bounds['start']->toDateString())
            ->whereDate('memo_date', '<=', $bounds['end']->toDateString());

        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }

        return $q->get()->map(fn (NonTravelMemo $m) => [
            'source_id' => (int) $m->id,
            'source_type' => NonTravelMemo::class,
            'type_label' => 'Non-travel',
            'title' => (string) ($m->activity_title ?? ''),
            'document_number' => $m->document_number,
            'division_id' => $m->division_id,
            'fund_type_id' => $m->fund_type_id !== null ? (int) $m->fund_type_id : null,
            'is_external_funded' => in_array((int) ($m->fund_type_id ?? 0), self::EXTERNAL_FUND_TYPE_IDS, true),
            'start_date' => optional($m->memo_date)->toDateString(),
            'end_date' => optional($m->memo_date)->toDateString(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichWithStatuses(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $byType = $rows->groupBy('source_type');
        $registry = collect();
        $reports = collect();

        foreach ($byType as $type => $group) {
            $ids = $group->pluck('source_id')->all();
            ReportingRegistryStatus::query()
                ->where('source_type', $type)
                ->whereIn('source_id', $ids)
                ->get()
                ->each(function ($r) use (&$registry) {
                    $registry[$r->source_type.'#'.$r->source_id] = $r;
                });
            ActivityReport::query()
                ->where('source_type', $type)
                ->whereIn('source_id', $ids)
                ->get(['id', 'source_id', 'source_type', 'overall_status', 'not_executed', 'document_number'])
                ->each(function ($r) use (&$reports) {
                    $reports[$r->source_type.'#'.$r->source_id] = $r;
                });
        }

        return $rows->map(function (array $row) use ($registry, $reports) {
            $key = $row['source_type'].'#'.$row['source_id'];
            /** @var ReportingRegistryStatus|null $reg */
            $reg = $registry[$key] ?? null;
            /** @var ActivityReport|null $report */
            $report = $reports[$key] ?? null;

            $process = 'pending';
            if ($reg?->processed_at) {
                $process = 'processed';
            } elseif ($reg?->started_at) {
                $process = 'started';
            }

            $reportStatus = 'none';
            if ($report) {
                if ($report->not_executed) {
                    $reportStatus = 'not_executed';
                } else {
                    $reportStatus = (string) ($report->overall_status ?: 'draft');
                }
            }

            $row['process_status'] = $process;
            $row['report_status'] = $reportStatus;
            $row['report_id'] = $report?->id;
            $row['started_at'] = optional($reg?->started_at)?->toIso8601String();
            $row['processed_at'] = optional($reg?->processed_at)?->toIso8601String();

            return $row;
        });
    }
}
