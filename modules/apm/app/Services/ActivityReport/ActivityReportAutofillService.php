<?php

namespace App\Services\ActivityReport;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ActivityReportAutofillService
{
    /**
     * Build ActivityReport attribute bag from a source memo.
     *
     * @return array<string, mixed>
     */
    public function fromSource(Model $source): array
    {
        $start = $this->asDate($source->date_from ?? $source->start_date ?? $source->memo_date ?? null);
        $end = $this->asDate($source->date_to ?? $source->end_date ?? $source->memo_date ?? null);
        $duration = null;
        if ($start && $end) {
            $duration = (int) $start->diffInDays($end) + 1;
        }

        $title = (string) ($source->activity_title ?? $source->title ?? '');
        $code = (string) ($source->workplan_activity_code ?? $source->activity_code ?? '');
        $activityNameCode = trim($code !== '' ? $code.($title !== '' ? ' — '.$title : '') : $title);

        $objectives = $source->background
            ?? $source->justification
            ?? $source->objectives
            ?? null;

        return array_filter([
            'division_id' => $source->division_id ?? null,
            'staff_id' => $source->staff_id ?? null,
            'responsible_person_id' => $source->responsible_person_id ?? $source->staff_id ?? null,
            'activity_name_code' => $activityNameCode !== '' ? $activityNameCode : null,
            'broad_activity_amert' => $source->key_result_area ?? null,
            'start_date' => $start?->toDateString(),
            'end_date' => $end?->toDateString(),
            'duration_days' => $duration,
            'objectives' => is_string($objectives) ? $objectives : null,
            'tier2' => $source->tier2 ?? null,
            'tier4' => $source->tier4 ?? null,
            'logistics_planned' => $this->stringifyBudget($source->budget_breakdown ?? null),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringifyBudget(mixed $breakdown): ?string
    {
        if ($breakdown === null || $breakdown === '') {
            return null;
        }
        if (is_string($breakdown)) {
            return $breakdown;
        }
        if (is_array($breakdown)) {
            return json_encode($breakdown, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
