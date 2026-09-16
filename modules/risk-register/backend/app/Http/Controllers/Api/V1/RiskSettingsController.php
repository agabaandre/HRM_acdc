<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskSettingsService;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskSettingsController extends Controller
{
    public function __construct(private readonly RiskSettingsService $settings) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->all(),
            'can_manage' => RiskPermissions::canManage($request->attributes->get('risk_permissions', [])),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'import_enabled' => 'sometimes|boolean',
        ]);
        if (array_key_exists('import_enabled', $data)) {
            $this->settings->setImportEnabled((bool) $data['import_enabled']);
        }

        return response()->json(['data' => $this->settings->all()]);
    }

    public function updateLookup(Request $request, string $table): JsonResponse
    {
        $this->assertManage($request);

        $allowed = [
            'rr_likelihoods' => ['label', 'score', 'sort_order'],
            'rr_impacts' => ['label', 'score', 'sort_order'],
            'rr_risk_types' => ['name', 'sort_order'],
            'rr_enterprise_themes' => ['code', 'name', 'sort_order'],
            'rr_statuses' => ['name', 'sort_order'],
            'rr_mitigation_effectiveness' => ['name', 'likelihood_reduction', 'is_assessed', 'sort_order'],
            'rr_rating_bands' => ['rating', 'min_score', 'max_score', 'sort_order'],
        ];
        if (! isset($allowed[$table])) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        $data = $request->validate([
            'id' => 'nullable|integer',
            'fields' => 'required|array',
        ]);

        $fields = array_intersect_key($data['fields'], array_flip($allowed[$table]));
        if ($fields === []) {
            return response()->json(['message' => 'No valid fields.'], 422);
        }

        if (! empty($data['id'])) {
            DB::table($table)->where('id', (int) $data['id'])->update($fields + ['updated_at' => now()]);
            $id = (int) $data['id'];
        } else {
            $id = (int) DB::table($table)->insertGetId($fields + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['data' => DB::table($table)->where('id', $id)->first()]);
    }

    public function deleteLookup(Request $request, string $table, int $id): JsonResponse
    {
        $this->assertManage($request);
        $allowed = [
            'rr_likelihoods', 'rr_impacts', 'rr_risk_types', 'rr_enterprise_themes',
            'rr_statuses', 'rr_mitigation_effectiveness', 'rr_rating_bands',
        ];
        if (! in_array($table, $allowed, true)) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }
        DB::table($table)->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function assertManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Forbidden.'], 403));
        }
    }
}
