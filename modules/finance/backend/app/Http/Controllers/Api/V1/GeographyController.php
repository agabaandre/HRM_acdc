<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceGeographyService;
use App\Support\FinancePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeographyController extends Controller
{
    public function __construct(private readonly FinanceGeographyService $geo = new FinanceGeographyService) {}

    public function show(Request $request): JsonResponse
    {
        $this->geo->seedRegionsIfEmpty();
        $this->geo->ensureRegionAliases();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->geo->snapshot(), [
                'can_edit' => FinancePermissions::canManageFundingPortfolio(
                    $request->attributes->get('risk_permissions', [])
                ),
            ]),
        ]);
    }

    public function saveAliases(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'region_aliases' => ['required', 'array'],
            'region_aliases.*.label' => ['required', 'string', 'max:255'],
            'region_aliases.*.region_id' => ['nullable', 'integer'],
            'region_aliases.*.match_source' => ['nullable', 'string', 'max:32'],
        ]);
        $aliases = $this->geo->saveRegionAliases($validated['region_aliases']);

        return response()->json([
            'success' => true,
            'message' => 'Region aliases saved.',
            'data' => ['region_aliases' => $aliases],
        ]);
    }

    public function storeRegion(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        if (! Schema::hasTable('regions')) {
            return response()->json(['success' => false, 'message' => 'Regions table missing.'], 500);
        }
        $validated = $request->validate(['name' => ['required', 'string', 'max:191']]);
        $idCol = Schema::hasColumn('regions', 'region_id') ? 'region_id' : 'id';
        $nameCol = Schema::hasColumn('regions', 'region_name') ? 'region_name' : 'name';
        $id = DB::table('regions')->insertGetId([$nameCol => trim($validated['name'])], $idCol);

        return response()->json(['success' => true, 'message' => 'Region added.', 'data' => ['id' => $id]], 201);
    }

    public function updateRegion(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate(['name' => ['required', 'string', 'max:191']]);
        $idCol = Schema::hasColumn('regions', 'region_id') ? 'region_id' : 'id';
        $nameCol = Schema::hasColumn('regions', 'region_name') ? 'region_name' : 'name';
        DB::table('regions')->where($idCol, $id)->update([$nameCol => trim($validated['name'])]);

        return response()->json(['success' => true, 'message' => 'Region updated.']);
    }

    public function storeCountry(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        if (! Schema::hasTable('nationalities')) {
            return response()->json(['success' => false, 'message' => 'Countries table missing.'], 500);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'region_id' => ['nullable', 'integer'],
            'iso2' => ['nullable', 'string', 'max:8'],
        ]);
        $payload = [
            'nationality' => trim($validated['name']),
            'continent' => 'AFRICA',
            'region_id' => (int) ($validated['region_id'] ?? 0),
            'iso2' => $validated['iso2'] ?? null,
        ];
        if (Schema::hasColumn('nationalities', 'nationality_name')) {
            $payload['nationality_name'] = trim($validated['name']);
        }
        $id = DB::table('nationalities')->insertGetId($payload, 'nationality_id');

        return response()->json(['success' => true, 'message' => 'Country added.', 'data' => ['id' => $id]], 201);
    }

    public function updateCountry(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'region_id' => ['nullable', 'integer'],
            'iso2' => ['nullable', 'string', 'max:8'],
        ]);
        DB::table('nationalities')->where('nationality_id', $id)->update([
            'nationality' => trim($validated['name']),
            'region_id' => $validated['region_id'] ?? null,
            'iso2' => $validated['iso2'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Country updated.']);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        if (! Schema::hasTable('duty_stations')) {
            return response()->json(['success' => false, 'message' => 'Locations table missing.'], 500);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'country_iso2' => ['nullable', 'string', 'max:8'],
            'city' => ['nullable', 'string', 'max:191'],
        ]);
        $payload = [
            'duty_station_name' => trim($validated['name']),
            'country' => $validated['country'] ?? '',
            'city' => $validated['city'] ?? '',
            'type' => '',
        ];
        if (Schema::hasColumn('duty_stations', 'country_iso2')) {
            $payload['country_iso2'] = $validated['country_iso2'] ?? null;
        }
        $id = DB::table('duty_stations')->insertGetId($payload, 'duty_station_id');

        return response()->json(['success' => true, 'message' => 'Location added.', 'data' => ['id' => $id]], 201);
    }

    public function updateLocation(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'country_iso2' => ['nullable', 'string', 'max:8'],
            'city' => ['nullable', 'string', 'max:191'],
        ]);
        $payload = [
            'duty_station_name' => trim($validated['name']),
            'country' => $validated['country'] ?? '',
            'city' => $validated['city'] ?? '',
        ];
        if (Schema::hasColumn('duty_stations', 'country_iso2')) {
            $payload['country_iso2'] = $validated['country_iso2'] ?? null;
        }
        DB::table('duty_stations')->where('duty_station_id', $id)->update($payload);

        return response()->json(['success' => true, 'message' => 'Location updated.']);
    }

    private function authorizeManage(Request $request): void
    {
        if (! FinancePermissions::canManageFundingPortfolio($request->attributes->get('risk_permissions', []))) {
            abort(response()->json([
                'success' => false,
                'message' => 'Managing geography requires the manage_funding_portfolio permission.',
            ], 403));
        }
    }
}
