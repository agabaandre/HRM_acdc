<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskWriteService;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        $divisionId = (int) $request->attributes->get('risk_division_id', 0);
        $filterDivision = $request->query('division_id');

        $q = DB::table('rr_risks')->orderByDesc('id');

        if (! RiskPermissions::canViewAll($permissions)) {
            if (! RiskPermissions::canViewDivision($permissions) || $divisionId < 1) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $q->where('division_id', $divisionId);
        } elseif ($filterDivision !== null && $filterDivision !== '') {
            $q->where('division_id', (int) $filterDivision);
        }

        $rows = $q->limit(500)->get();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $risk = DB::table('rr_risks')->where('id', $id)->first();
        if (! $risk) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! $this->canViewRisk($request, $risk)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $owners = DB::table('rr_risk_owners')->where('risk_id', $id)->get();
        $audit = DB::table('rr_audit_logs')
            ->where('entity_type', 'rr_risks')
            ->where('entity_id', $id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $risk,
            'owners' => $owners,
            'audit' => $audit,
        ]);
    }

    public function store(Request $request, RiskWriteService $writer): JsonResponse
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        if (! RiskPermissions::canManage($permissions) && ! RiskPermissions::canViewDivision($permissions)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:512',
            'division_id' => 'nullable|integer',
            'directorate_id' => 'nullable|integer',
            'enterprise_theme_id' => 'nullable|integer',
            'risk_type_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'mitigation_effectiveness_id' => 'nullable|integer',
            'inherent_likelihood' => 'nullable|integer|min:1|max:5',
            'inherent_impact' => 'nullable|integer|min:1|max:5',
            'consequence' => 'nullable|string',
            'root_causes' => 'nullable|string',
            'mitigation' => 'nullable|string',
            'management_response' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
            'action_update' => 'nullable|string',
            'date_of_update' => 'nullable|date',
            'oio_verification_notes' => 'nullable|string',
            'owner_staff_ids' => 'nullable|array',
            'owner_staff_ids.*' => 'integer',
        ]);

        try {
            $created = $writer->create(
                $data,
                (int) $request->attributes->get('risk_staff_id'),
                $permissions
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage() === 'Forbidden' ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $code);
        }

        return response()->json(['data' => $created], 201);
    }

    public function update(Request $request, int $id, RiskWriteService $writer): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:512',
            'division_id' => 'nullable|integer',
            'directorate_id' => 'nullable|integer',
            'enterprise_theme_id' => 'nullable|integer',
            'risk_type_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'mitigation_effectiveness_id' => 'nullable|integer',
            'inherent_likelihood' => 'nullable|integer|min:1|max:5',
            'inherent_impact' => 'nullable|integer|min:1|max:5',
            'consequence' => 'nullable|string',
            'root_causes' => 'nullable|string',
            'mitigation' => 'nullable|string',
            'management_response' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
            'action_update' => 'nullable|string',
            'date_of_update' => 'nullable|date',
            'oio_verification_notes' => 'nullable|string',
            'owner_staff_ids' => 'nullable|array',
            'owner_staff_ids.*' => 'integer',
        ]);

        try {
            $updated = $writer->update(
                $id,
                $data,
                (int) $request->attributes->get('risk_staff_id'),
                $request->attributes->get('risk_permissions', [])
            );
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $code = match ($msg) {
                'Forbidden' => 403,
                'Not found' => 404,
                default => 422,
            };

            return response()->json(['message' => $msg], $code);
        }

        return response()->json(['data' => $updated]);
    }

    private function canViewRisk(Request $request, object $risk): bool
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        if (RiskPermissions::canViewAll($permissions)) {
            return true;
        }
        if (! RiskPermissions::canViewDivision($permissions)) {
            return false;
        }
        $divisionId = (int) $request->attributes->get('risk_division_id', 0);

        return $divisionId > 0 && (int) ($risk->division_id ?? 0) === $divisionId;
    }
}
