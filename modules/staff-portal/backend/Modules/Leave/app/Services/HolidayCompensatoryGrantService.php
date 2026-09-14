<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Models\StaffLeaveCompensatoryCredit;

class HolidayCompensatoryGrantService
{
    public function __construct(
        protected HolidayCalendarService $calendar,
        protected LeavePolicyService $policy,
    ) {}

    /**
     * @return array{staff: int, granted: int, skipped: int}
     */
    public function grantAll(?int $year = null, ?string $through = null): array
    {
        $year = $year ?? (int) now()->year;
        $granted = 0;
        $skipped = 0;
        $staffIds = $this->eligibleStaffIds();

        foreach ($staffIds as $staffId) {
            $created = $this->grantForStaff($staffId, $year, $through);
            if ($created > 0) {
                $granted += $created;
            } else {
                $skipped++;
            }
        }

        return [
            'staff' => count($staffIds),
            'granted' => $granted,
            'skipped' => $skipped,
        ];
    }

    public function grantForStaff(int $staffId, int $year, ?string $through = null): int
    {
        $through = $through ?? now()->toDateString();
        $cap = max(0, (int) $this->policy->get('holiday_compensatory_max_days_per_year', 15));
        $ctx = $this->calendar->staffContext($staffId);
        $stationId = (int) ($ctx['duty_station_id'] ?? 0);
        $created = 0;

        foreach ($this->calendar->occurrencesForStaff($staffId, $year) as $occ) {
            if (($occ['date'] ?? '') > $through) {
                continue;
            }
            if (empty($occ['grants_compensatory_if_weekend'])) {
                continue;
            }
            $date = Carbon::parse((string) $occ['date'])->startOfDay();
            if (! $date->isWeekend()) {
                continue;
            }

            $allowedStations = $occ['compensatory_duty_station_ids'] ?? null;
            if (is_array($allowedStations) && $allowedStations !== []) {
                $allowedStations = array_map('intval', $allowedStations);
                if (! in_array($stationId, $allowedStations, true)) {
                    continue;
                }
            }

            $already = $this->holidayDaysGranted($staffId, $year);
            if ($already >= $cap) {
                break;
            }

            $exists = StaffLeaveCompensatoryCredit::query()
                ->where('staff_id', $staffId)
                ->where('kind', 'holiday')
                ->whereDate('source_date', $date->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            StaffLeaveCompensatoryCredit::query()->create([
                'staff_id' => $staffId,
                'kind' => 'holiday',
                'days' => 1,
                'days_used' => 0,
                'reason' => ((string) ($occ['name'] ?? 'Public holiday')).' (weekend)',
                'granted_on' => now()->toDateString(),
                'expires_on' => sprintf('%d-12-31', $year),
                'source_holiday_rule_id' => $occ['rule_id'] ?? null,
                'source_date' => $date->toDateString(),
            ]);
            $created++;
        }

        return $created;
    }

    public function consume(int $staffId, string $kind, float $days): float
    {
        if ($days <= 0) {
            return 0.0;
        }

        $left = $days;
        $rows = StaffLeaveCompensatoryCredit::query()
            ->where('staff_id', $staffId)
            ->where('kind', $kind)
            ->where(function ($q): void {
                $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $take = min($row->remainingDays(), $left);
            if ($take <= 0) {
                continue;
            }
            $row->days_used = (float) $row->days_used + $take;
            $row->save();
            $left -= $take;
            if ($left <= 0) {
                break;
            }
        }

        return round($days - $left, 2);
    }

    public function holidayDaysGranted(int $staffId, int $year): float
    {
        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        return (float) StaffLeaveCompensatoryCredit::query()
            ->where('staff_id', $staffId)
            ->where('kind', 'holiday')
            ->whereBetween('source_date', [$start, $end])
            ->sum('days');
    }

    /**
     * @return list<int>
     */
    public function eligibleStaffIds(): array
    {
        $latest = '(SELECT staff_id, MAX(staff_contract_id) AS cid FROM staff_contracts GROUP BY staff_id)';

        return DB::table('staff as s')
            ->join(DB::raw($latest.' as latest'), 'latest.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'latest.cid')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->orderBy('s.staff_id')
            ->pluck('s.staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
