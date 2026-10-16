<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\OrgStructureService;

class OrgStructureController extends Controller
{
    public function show(OrgStructureService $org): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json(['data' => $org->tree()]);
    }

    public function generate(Request $request, OrgStructureService $org): JsonResponse
    {
        PortalPermission::authorize(15);

        $validated = $request->validate([
            'replace' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $org->generateFromSystem((bool) ($validated['replace'] ?? true));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
            'tree' => $org->tree(),
        ]);
    }

    public function updateNode(int $id, Request $request, OrgStructureService $org): JsonResponse
    {
        PortalPermission::authorize(15);

        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'approved_slots' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'integer', 'in:0,1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tier' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        try {
            $node = $org->updateNode($id, $payload);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Position updated.',
            'data' => $node,
            'tree' => $org->tree(),
        ]);
    }
}
