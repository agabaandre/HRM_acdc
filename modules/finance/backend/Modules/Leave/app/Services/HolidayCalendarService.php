<?php

namespace Modules\Leave\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Models\LeaveHolidayRule;
use Modules\Staff\Models\Staff;
use Modules\Staff\Models\StaffContract;

class HolidayCalendarService
{
    public function __construct(
        protected HolidayRuleOccurrenceExpander $expander,
    ) {}

    /**
     * @return array{
     *     duty_station_id: ?int,
     *     country_iso2: ?string,
     *     nationality_iso2: ?string,
     *     independence_month: ?int,
     *     independence_day: ?int
     * }
     */
    public function staffContext(int $staffId): array
    {
        $staff = Staff::query()->find($staffId);
        $nationality = null;
        if ($staff && $staff->nationality_id) {
            $nationality = DB::table('nationalities')->where('nationality_id', $staff->nationality_id)->first();
        }

        $contract = StaffContract::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->first();

        $station = null;
        if ($contract && $contract->duty_station_id) {
            $station = DB::table('duty_stations')->where('duty_station_id', $contract->duty_station_id)->first();
        }

        return [
            'duty_station_id' => $station ? (int) $station->duty_station_id : null,
            'country_iso2' => $this->resolveDutyStationIso2($station),
            'nationality_iso2' => $nationality && ! empty($nationality->iso2)
                ? strtoupper((string) $nationality->iso2)
                : null,
            'independence_month' => $nationality && property_exists($nationality, 'independence_month') && $nationality->independence_month
                ? (int) $nationality->independence_month
                : null,
            'independence_day' => $nationality && property_exists($nationality, 'independence_day') && $nationality->independence_day
                ? (int) $nationality->independence_day
                : null,
        ];
    }

    public function resolveDutyStationIso2(mixed $station): ?string
    {
        if ($station === null) {
            return null;
        }

        $iso = strtoupper(trim((string) ($station->country_iso2 ?? '')));
        if (strlen($iso) === 2) {
            return $iso;
        }

        $country = trim((string) ($station->country ?? ''));
        if ($country === '') {
            return null;
        }
        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        $row = DB::table('nationalities')
            ->where(function ($q) use ($country): void {
                $q->whereRaw('UPPER(iso2) = ?', [strtoupper($country)])
                    ->orWhereRaw('UPPER(iso3) = ?', [strtoupper($country)])
                    ->orWhere('nationality', $country);
                if (Schema::hasColumn('nationalities', 'nationality_name')) {
                    $q->orWhere('nationality_name', $country);
                }
            })
            ->first();

        $matched = $row && ! empty($row->iso2) ? strtoupper((string) $row->iso2) : null;

        return $matched !== '' ? $matched : null;
    }

    /**
     * @return list<array{
     *     date: string,
     *     name: string,
     *     grants_compensatory_if_weekend: bool,
     *     compensatory_duty_station_ids: ?list<int>,
     *     rule_id: ?int,
     *     source: string
     * }>
     */
    public function occurrencesForStaff(int $staffId, int $year): array
    {
        return $this->occurrencesForContext($this->staffContext($staffId), $year);
    }

    /**
     * @param  array{duty_station_id: ?int, country_iso2: ?string, nationality_iso2: ?string, independence_month: ?int, independence_day: ?int}  $ctx
     * @return list<array{
     *     date: string,
     *     name: string,
     *     grants_compensatory_if_weekend: bool,
     *     compensatory_duty_station_ids: ?list<int>,
     *     rule_id: ?int,
     *     source: string
     * }>
     */
    public function occurrencesForContext(array $ctx, int $year): array
    {
        $out = [];

        $rules = LeaveHolidayRule::query()->where('is_active', true)->get();
        foreach ($rules as $rule) {
            if (! $this->ruleApplies($rule, $ctx)) {
                continue;
            }
            $date = $this->expander->dateForRule($rule->toOccurrenceArray(), $year);
            if ($date === null) {
                continue;
            }
            $out[$date] = $this->mergeOccurrence($out[$date] ?? null, [
                'date' => $date,
                'name' => (string) $rule->name,
                'grants_compensatory_if_weekend' => (bool) $rule->grants_compensatory_if_weekend,
                'compensatory_duty_station_ids' => $this->stationIdList($rule->compensatory_duty_station_ids),
                'rule_id' => (int) $rule->id,
                'source' => (string) ($rule->source ?: 'manual'),
            ]);
        }

        $month = $ctx['independence_month'];
        $day = $ctx['independence_day'];
        if ($month && $day) {
            $date = $this->expander->dateForRule([
                'recurrence' => 'yearly_md',
                'month' => $month,
                'day' => $day,
            ], $year);
            if ($date !== null) {
                $iso = $ctx['nationality_iso2'] ?? '';
                $out[$date] = $this->mergeOccurrence($out[$date] ?? null, [
                    'date' => $date,
                    'name' => $iso !== '' ? $iso.' Independence Day' : 'Independence Day',
                    'grants_compensatory_if_weekend' => true,
                    'compensatory_duty_station_ids' => null,
                    'rule_id' => null,
                    'source' => 'independence',
                ]);
            }
        }

        ksort($out);

        return array_values($out);
    }

    /**
     * @return list<string>
     */
    public function holidayDatesForStaff(int $staffId, int $year): array
    {
        return array_values(array_map(
            static fn (array $row): string => $row['date'],
            $this->occurrencesForStaff($staffId, $year),
        ));
    }

    public function isHoliday(int $staffId, string $date): bool
    {
        $year = (int) substr($date, 0, 4);

        return in_array($date, $this->holidayDatesForStaff($staffId, $year), true);
    }

    /**
     * @param  array{duty_station_id: ?int, country_iso2: ?string}  $ctx
     */
    protected function ruleApplies(LeaveHolidayRule $rule, array $ctx): bool
    {
        $scope = (string) $rule->scope;
        if ($scope === 'global') {
            return true;
        }
        if ($scope === 'country') {
            $iso = strtoupper((string) ($rule->country_iso2 ?? ''));

            return $iso !== '' && $iso === (string) ($ctx['country_iso2'] ?? '');
        }
        if ($scope === 'duty_station') {
            return $rule->duty_station_id
                && (int) $rule->duty_station_id === (int) ($ctx['duty_station_id'] ?? 0);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeOccurrence(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }

        $existingStations = $this->stationIdList($existing['compensatory_duty_station_ids'] ?? null);
        $incomingStations = $this->stationIdList($incoming['compensatory_duty_station_ids'] ?? null);

        $stations = null;
        if ($existingStations !== null && $incomingStations !== null) {
            $stations = array_values(array_unique(array_merge($existingStations, $incomingStations)));
        }

        return [
            'date' => $incoming['date'],
            'name' => $existing['name'].' / '.$incoming['name'],
            'grants_compensatory_if_weekend' => (bool) $existing['grants_compensatory_if_weekend']
                || (bool) $incoming['grants_compensatory_if_weekend'],
            'compensatory_duty_station_ids' => $stations,
            'rule_id' => $incoming['rule_id'] ?? $existing['rule_id'],
            'source' => (string) $incoming['source'],
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<int>|null
     */
    protected function stationIdList(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_map('intval', $value));
    }
}
