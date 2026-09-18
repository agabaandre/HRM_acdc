<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Http\Resources\Api\V1\LeaveHolidayRuleResource;
use Modules\Leave\Models\LeaveHolidayRule;
use Modules\Leave\Services\HolidayCalendarService;
use Modules\Leave\Services\OpenHolidaysClient;
use Modules\Leave\Support\LeaveAccess;

class LeaveHolidaySettingsController extends Controller
{
    public function index(): JsonResponse
    {
        LeaveAccess::authorizeHolidays();

        $rules = LeaveHolidayRule::query()
            ->orderBy('scope')
            ->orderBy('country_iso2')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => LeaveHolidayRuleResource::collection($rules)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $rule = LeaveHolidayRule::query()->create($this->validatedRule($request) + ['source' => 'manual']);
        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Holiday rule created.',
            'data' => new LeaveHolidayRuleResource($rule),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $rule = LeaveHolidayRule::query()->findOrFail($id);
        $rule->update($this->validatedRule($request));
        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Holiday rule updated.',
            'data' => new LeaveHolidayRuleResource($rule->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        LeaveHolidayRule::query()->findOrFail($id)->delete();
        PortalReadCache::bust('leave');

        return response()->json(['message' => 'Holiday rule deleted.']);
    }

    public function preview(Request $request, HolidayCalendarService $calendar): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $data = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
            'country_iso2' => 'nullable|string|size:2',
            'duty_station_id' => 'nullable|integer|min:1',
        ]);
        $year = (int) ($data['year'] ?? now()->year);
        $stationId = isset($data['duty_station_id']) ? (int) $data['duty_station_id'] : null;
        $iso = isset($data['country_iso2']) ? strtoupper($data['country_iso2']) : null;
        if ($stationId && Schema::hasTable('duty_stations')) {
            $station = DB::table('duty_stations')->where('duty_station_id', $stationId)->first();
            $iso = $calendar->resolveDutyStationIso2($station) ?? $iso;
        }

        $occurrences = $calendar->occurrencesForContext([
            'duty_station_id' => $stationId,
            'country_iso2' => $iso,
            'nationality_iso2' => null,
            'independence_month' => null,
            'independence_day' => null,
        ], $year);

        return response()->json([
            'data' => [
                'year' => $year,
                'country_iso2' => $iso,
                'duty_station_id' => $stationId,
                'holidays' => $occurrences,
            ],
        ]);
    }

    public function openHolidaysCountries(OpenHolidaysClient $client): JsonResponse
    {
        LeaveAccess::authorizeHolidays();

        return response()->json(['data' => $client->countries()]);
    }

    public function openHolidaysPreview(Request $request, OpenHolidaysClient $client): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $data = $request->validate([
            'country_iso2' => 'required|string|size:2',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);
        $year = (int) ($data['year'] ?? now()->year);

        return response()->json([
            'data' => $client->publicHolidays(strtoupper($data['country_iso2']), $year),
        ]);
    }

    public function openHolidaysImport(Request $request, OpenHolidaysClient $client): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $data = $request->validate([
            'country_iso2' => 'required|string|size:2',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);
        $iso = strtoupper($data['country_iso2']);
        $year = (int) ($data['year'] ?? now()->year);
        $created = 0;
        $skipped = 0;

        foreach ($client->publicHolidays($iso, $year) as $row) {
            if ($row['openholidays_id'] !== '' && LeaveHolidayRule::query()
                ->where('openholidays_id', $row['openholidays_id'])
                ->exists()) {
                $skipped++;

                continue;
            }

            $start = $row['start_date'];
            $month = (int) substr($start, 5, 2);
            $day = (int) substr($start, 8, 2);
            $duplicate = LeaveHolidayRule::query()
                ->where('scope', 'country')
                ->where('country_iso2', $iso)
                ->where('name', $row['name'])
                ->where(function ($q) use ($row, $month, $day, $start): void {
                    if ($row['recurrence'] === 'yearly_md') {
                        $q->where('month', $month)->where('day', $day);
                    } else {
                        $q->whereDate('once_date', $start);
                    }
                })
                ->exists();
            if ($duplicate) {
                $skipped++;

                continue;
            }

            LeaveHolidayRule::query()->create([
                'name' => $row['name'],
                'recurrence' => $row['recurrence'],
                'month' => $row['recurrence'] === 'yearly_md' ? $month : null,
                'day' => $row['recurrence'] === 'yearly_md' ? $day : null,
                'once_date' => $row['recurrence'] === 'once' ? $start : null,
                'scope' => 'country',
                'country_iso2' => $iso,
                'grants_compensatory_if_weekend' => true,
                'source' => 'openholidays',
                'openholidays_id' => $row['openholidays_id'] ?: null,
                'is_movable' => $row['is_movable'],
                'is_active' => true,
            ]);
            $created++;
        }

        PortalReadCache::bust('leave');

        return response()->json([
            'message' => "Imported {$created} holiday(s); skipped {$skipped}.",
            'data' => ['created' => $created, 'skipped' => $skipped],
        ]);
    }

    public function independenceIndex(): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        if (! Schema::hasTable('nationalities')) {
            return response()->json(['data' => []]);
        }

        $cols = ['nationality_id', 'nationality', 'iso2'];
        if (Schema::hasColumn('nationalities', 'independence_month')) {
            $cols[] = 'independence_month';
            $cols[] = 'independence_day';
        }

        $rows = DB::table('nationalities')->orderBy('nationality')->get($cols);

        return response()->json(['data' => $rows]);
    }

    public function independenceUpdate(Request $request): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $data = $request->validate([
            'rows' => 'required|array',
            'rows.*.nationality_id' => 'required|integer|min:1',
            'rows.*.independence_month' => 'nullable|integer|min:1|max:12',
            'rows.*.independence_day' => 'nullable|integer|min:1|max:31',
        ]);
        if (! Schema::hasColumn('nationalities', 'independence_month')) {
            return response()->json(['message' => 'Independence columns are not migrated yet.'], 422);
        }

        foreach ($data['rows'] as $row) {
            DB::table('nationalities')->where('nationality_id', $row['nationality_id'])->update([
                'independence_month' => $row['independence_month'] ?? null,
                'independence_day' => $row['independence_day'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Independence dates saved.']);
    }

    public function dutyStationsIndex(): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        if (! Schema::hasTable('duty_stations')) {
            return response()->json(['data' => []]);
        }

        $cols = ['duty_station_id', 'duty_station_name'];
        if (Schema::hasColumn('duty_stations', 'country')) {
            $cols[] = 'country';
        }
        if (Schema::hasColumn('duty_stations', 'country_iso2')) {
            $cols[] = 'country_iso2';
        }

        return response()->json([
            'data' => DB::table('duty_stations')->orderBy('duty_station_name')->get($cols),
        ]);
    }

    public function dutyStationsUpdate(Request $request): JsonResponse
    {
        LeaveAccess::authorizeHolidays();
        $data = $request->validate([
            'rows' => 'required|array',
            'rows.*.duty_station_id' => 'required|integer|min:1',
            'rows.*.country_iso2' => 'nullable|string|max:2',
        ]);
        if (! Schema::hasColumn('duty_stations', 'country_iso2')) {
            return response()->json(['message' => 'Duty station ISO columns are not migrated yet.'], 422);
        }

        foreach ($data['rows'] as $row) {
            $iso = strtoupper(trim((string) ($row['country_iso2'] ?? '')));
            DB::table('duty_stations')->where('duty_station_id', $row['duty_station_id'])->update([
                'country_iso2' => $iso !== '' ? $iso : null,
            ]);
        }

        return response()->json(['message' => 'Duty station countries saved.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:80',
            'recurrence' => 'required|in:yearly_md,once',
            'month' => 'nullable|integer|min:1|max:12',
            'day' => 'nullable|integer|min:1|max:31',
            'once_date' => 'nullable|date',
            'scope' => 'required|in:global,country,duty_station',
            'country_iso2' => 'nullable|string|size:2',
            'duty_station_id' => 'nullable|integer|min:1',
            'grants_compensatory_if_weekend' => 'boolean',
            'compensatory_duty_station_ids' => 'nullable|array',
            'compensatory_duty_station_ids.*' => 'integer|min:1',
            'is_movable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $iso = isset($validated['country_iso2']) ? strtoupper($validated['country_iso2']) : null;

        return [
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'recurrence' => $validated['recurrence'],
            'month' => $validated['recurrence'] === 'yearly_md' ? ($validated['month'] ?? null) : null,
            'day' => $validated['recurrence'] === 'yearly_md' ? ($validated['day'] ?? null) : null,
            'once_date' => $validated['recurrence'] === 'once' ? ($validated['once_date'] ?? null) : null,
            'scope' => $validated['scope'],
            'country_iso2' => $validated['scope'] === 'country' ? $iso : null,
            'duty_station_id' => $validated['scope'] === 'duty_station' ? ($validated['duty_station_id'] ?? null) : null,
            'grants_compensatory_if_weekend' => (bool) ($validated['grants_compensatory_if_weekend'] ?? true),
            'compensatory_duty_station_ids' => $validated['compensatory_duty_station_ids'] ?? null,
            'is_movable' => (bool) ($validated['is_movable'] ?? false),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }
}
