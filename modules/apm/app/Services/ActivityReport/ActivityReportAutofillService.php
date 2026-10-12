<?php

namespace App\Services\ActivityReport;

use App\Models\Activity;
use App\Models\FundCode;
use App\Models\Location;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Support\BudgetBreakdownTotal;
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

        $attrs = array_filter([
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
            'location_venue' => $this->resolveLocationVenue($source),
        ], fn ($v) => $v !== null && $v !== '');

        if ($this->supportsParticipants($source)) {
            $participants = $this->resolveParticipantsCount($source);
            if ($participants !== null) {
                $attrs['participants_count'] = $participants;
            }
        }

        if (! $this->isExternalFunded($source)) {
            $budgetCode = $this->resolveBudgetCode($source);
            if ($budgetCode !== null && $budgetCode !== '') {
                $attrs['budget_code'] = $budgetCode;
            }
            $total = $this->resolveTotalCost($source);
            if ($total !== null) {
                $attrs['total_cost_usd'] = $total;
            }
            $planned = $this->stringifyBudget($source->budget_breakdown ?? null);
            if ($planned !== null && $planned !== '') {
                $attrs['logistics_planned'] = $planned;
            }
        }

        return $attrs;
    }

    /**
     * Fill blank report fields from the parent memo (draft only).
     */
    public function fillEmptyFromSource(Model $report, Model $source): bool
    {
        $attrs = $this->fromSource($source);
        $changed = false;

        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $report->{$key} ?? null;
            if ($current !== null && $current !== '') {
                continue;
            }
            $report->{$key} = $value;
            $changed = true;
        }

        return $changed;
    }

    public function sourceKind(Model $source): string
    {
        return match (true) {
            $source instanceof NonTravelMemo => 'non_travel',
            $source instanceof SpecialMemo => 'special_memo',
            $source instanceof Activity => 'activity',
            default => 'unknown',
        };
    }

    public function isExternalFunded(Model $source): bool
    {
        $fundTypeId = (int) ($source->fund_type_id ?? 0);

        return in_array($fundTypeId, ActivityReportRegistryService::EXTERNAL_FUND_TYPE_IDS, true);
    }

    public function supportsParticipants(Model $source): bool
    {
        return ! ($source instanceof NonTravelMemo);
    }

    public function supportsBudget(Model $source): bool
    {
        return ! $this->isExternalFunded($source);
    }

    private function resolveLocationVenue(Model $source): ?string
    {
        $ids = $this->normalizeIdList($source->location_id ?? null);
        if ($ids === []) {
            $direct = trim((string) ($source->location ?? $source->venue ?? $source->location_venue ?? ''));

            return $direct !== '' ? $direct : null;
        }

        $names = Location::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn ($n) => is_string($n) && trim($n) !== '')
            ->values()
            ->all();

        if ($names === []) {
            return null;
        }

        return implode(', ', $names);
    }

    private function resolveParticipantsCount(Model $source): ?int
    {
        if (isset($source->total_participants) && $source->total_participants !== null && $source->total_participants !== '') {
            return max(0, (int) $source->total_participants);
        }

        $internal = $this->normalizeIdList($source->internal_participants ?? null);
        $external = (int) ($source->total_external_participants ?? 0);
        if ($internal !== [] || $external > 0) {
            return count($internal) + max(0, $external);
        }

        return null;
    }

    private function resolveBudgetCode(Model $source): ?string
    {
        $ids = $this->normalizeIdList($source->budget_id ?? null);
        if ($ids === []) {
            $direct = trim((string) ($source->budget_code ?? ''));

            return $direct !== '' ? $direct : null;
        }

        $codes = FundCode::query()
            ->whereIn('id', $ids)
            ->orderBy('code')
            ->pluck('code')
            ->filter(fn ($c) => is_string($c) && trim($c) !== '')
            ->values()
            ->all();

        if ($codes === []) {
            return null;
        }

        return mb_substr(implode(', ', $codes), 0, 100);
    }

    private function resolveTotalCost(Model $source): ?float
    {
        $breakdown = $source->budget_breakdown ?? null;
        if ($breakdown === null || $breakdown === '' || $breakdown === []) {
            return null;
        }

        $style = $source instanceof NonTravelMemo
            ? BudgetBreakdownTotal::STYLE_NON_TRAVEL
            : ($source instanceof SpecialMemo
                ? BudgetBreakdownTotal::STYLE_TRAVEL_LENIENT
                : BudgetBreakdownTotal::STYLE_TRAVEL_STRICT);

        $total = BudgetBreakdownTotal::memoGrandTotal($breakdown, $style);

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * @return list<int>
     */
    private function normalizeIdList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($v) => is_numeric($v) ? (int) $v : null,
            $raw
        ))));
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
