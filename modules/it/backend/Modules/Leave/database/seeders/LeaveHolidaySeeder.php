<?php

namespace Modules\Leave\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Models\LeaveHolidayRule;
use Modules\Leave\Support\IndependenceDayCatalog;

class LeaveHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRules();
        $this->seedIndependenceDays();
        $this->backfillDutyStationIso2();
    }

    protected function seedRules(): void
    {
        $meskelStations = [];
        if (Schema::hasTable('duty_stations')) {
            $meskelStations = DB::table('duty_stations')
                ->where(function ($q): void {
                    $q->whereRaw('LOWER(duty_station_name) = ?', ['hq'])
                        ->orWhereRaw('LOWER(duty_station_name) like ?', ['%panvac%'])
                        ->orWhereRaw('LOWER(duty_station_name) like ?', ['%headquarters%']);
                })
                ->pluck('duty_station_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $rules = [
            ['code' => 'global_new_year', 'name' => 'New Year', 'recurrence' => 'yearly_md', 'month' => 1, 'day' => 1, 'scope' => 'global'],
            ['code' => 'global_labour_day', 'name' => 'Labour Day', 'recurrence' => 'yearly_md', 'month' => 5, 'day' => 1, 'scope' => 'global'],
            ['code' => 'global_africa_day', 'name' => 'Africa Day', 'recurrence' => 'yearly_md', 'month' => 5, 'day' => 25, 'scope' => 'global'],
            ['code' => 'global_au_day', 'name' => 'AU Day', 'recurrence' => 'yearly_md', 'month' => 9, 'day' => 9, 'scope' => 'global'],
            ['code' => 'global_christmas', 'name' => 'International Christmas', 'recurrence' => 'yearly_md', 'month' => 12, 'day' => 25, 'scope' => 'global'],
            [
                'code' => 'global_good_friday',
                'name' => 'Good Friday (international)',
                'recurrence' => 'once',
                'once_date' => '2026-04-03',
                'scope' => 'global',
                'is_movable' => true,
            ],
            ['code' => 'et_christmas', 'name' => 'Ethiopian Christmas', 'recurrence' => 'yearly_md', 'month' => 1, 'day' => 7, 'scope' => 'country', 'country_iso2' => 'ET'],
            ['code' => 'et_timket', 'name' => 'Timket', 'recurrence' => 'yearly_md', 'month' => 1, 'day' => 19, 'scope' => 'country', 'country_iso2' => 'ET'],
            ['code' => 'et_adwa', 'name' => 'Adwa Victory Day', 'recurrence' => 'yearly_md', 'month' => 3, 'day' => 2, 'scope' => 'country', 'country_iso2' => 'ET'],
            ['code' => 'et_patriots', 'name' => 'Patriots Day', 'recurrence' => 'yearly_md', 'month' => 5, 'day' => 5, 'scope' => 'country', 'country_iso2' => 'ET'],
            ['code' => 'et_new_year', 'name' => 'Ethiopian New Year', 'recurrence' => 'yearly_md', 'month' => 9, 'day' => 11, 'scope' => 'country', 'country_iso2' => 'ET'],
            [
                'code' => 'et_meskel',
                'name' => 'Meskel',
                'recurrence' => 'yearly_md',
                'month' => 9,
                'day' => 27,
                'scope' => 'country',
                'country_iso2' => 'ET',
                'compensatory_duty_station_ids' => $meskelStations !== [] ? $meskelStations : null,
            ],
            [
                'code' => 'et_good_friday',
                'name' => 'Ethiopian Good Friday',
                'recurrence' => 'once',
                'once_date' => '2026-04-10',
                'scope' => 'country',
                'country_iso2' => 'ET',
                'is_movable' => true,
            ],
            [
                'code' => 'et_eid_fitr',
                'name' => 'Eid al-Fitr',
                'recurrence' => 'once',
                'scope' => 'country',
                'country_iso2' => 'ET',
                'is_movable' => true,
                'is_active' => false,
            ],
            [
                'code' => 'et_eid_adha',
                'name' => 'Eid al Adha',
                'recurrence' => 'once',
                'scope' => 'country',
                'country_iso2' => 'ET',
                'is_movable' => true,
                'is_active' => false,
            ],
            [
                'code' => 'et_mawlid',
                'name' => 'Mawlid',
                'recurrence' => 'once',
                'scope' => 'country',
                'country_iso2' => 'ET',
                'is_movable' => true,
                'is_active' => false,
            ],
        ];

        foreach ($rules as $row) {
            $payload = array_merge([
                'grants_compensatory_if_weekend' => true,
                'source' => 'seed',
                'is_movable' => false,
                'is_active' => true,
                'month' => null,
                'day' => null,
                'once_date' => null,
                'country_iso2' => null,
                'duty_station_id' => null,
                'compensatory_duty_station_ids' => null,
            ], $row);

            LeaveHolidayRule::query()->updateOrCreate(
                ['code' => $payload['code']],
                $payload,
            );
        }
    }

    protected function seedIndependenceDays(): void
    {
        if (! Schema::hasTable('nationalities') || ! Schema::hasColumn('nationalities', 'independence_month')) {
            return;
        }

        foreach (IndependenceDayCatalog::monthDayByIso2() as $iso => [$month, $day]) {
            DB::table('nationalities')
                ->whereRaw('UPPER(iso2) = ?', [$iso])
                ->whereNull('independence_month')
                ->update([
                    'independence_month' => $month,
                    'independence_day' => $day,
                ]);
        }
    }

    protected function backfillDutyStationIso2(): void
    {
        if (! Schema::hasTable('duty_stations') || ! Schema::hasColumn('duty_stations', 'country_iso2')) {
            return;
        }

        $stations = DB::table('duty_stations')->where(function ($q): void {
            $q->whereNull('country_iso2')->orWhere('country_iso2', '');
        })->get();

        $calendar = app(\Modules\Leave\Services\HolidayCalendarService::class);
        foreach ($stations as $station) {
            $iso = $calendar->resolveDutyStationIso2($station);
            if ($iso === null) {
                continue;
            }
            DB::table('duty_stations')->where('duty_station_id', $station->duty_station_id)->update([
                'country_iso2' => $iso,
            ]);
        }
    }
}
