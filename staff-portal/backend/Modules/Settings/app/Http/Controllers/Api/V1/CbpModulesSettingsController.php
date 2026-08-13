<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\CbpModulesAdminService;

class CbpModulesSettingsController extends Controller
{
    public function __construct(
        protected CbpModulesAdminService $modules,
    ) {}

    public function index(): JsonResponse
    {
        PortalPermission::authorize(15);

        $tableExists = $this->modules->tableExists();
        $rows = $tableExists ? $this->modules->allOrdered() : [];

        $data = array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'module_key' => (string) $row->module_key,
                'system_name' => (string) $row->system_name,
                'description' => (string) ($row->description ?? ''),
                'base_url' => (string) ($row->base_url ?? ''),
                'base_url_development' => (string) ($row->base_url_development ?? ''),
                'base_url_production' => (string) ($row->base_url_production ?? ''),
                'icon_class' => (string) ($row->icon_class ?? 'fa-th'),
                'permission_code' => (string) ($row->permission_code ?? ''),
                'uses_staff_portal_token' => (bool) ($row->uses_staff_portal_token ?? false),
                'is_production' => (bool) ($row->is_production ?? false),
                'is_enabled' => (bool) ($row->is_enabled ?? false),
                'show_in_apm_menu' => (bool) ($row->show_in_apm_menu ?? false),
                'alternate_base_url' => (string) ($row->alternate_base_url ?? ''),
                'alternate_for_role_id' => $row->alternate_for_role_id !== null ? (int) $row->alternate_for_role_id : null,
                'target_resolver' => (string) ($row->target_resolver ?? 'codeigniter'),
                'sort_order' => (int) ($row->sort_order ?? 0),
            ];
        }, $rows);

        return response()->json([
            'data' => $data,
            'meta' => [
                'table_exists' => $tableExists,
                'next_sort_order' => $this->modules->nextSortOrder(),
                'next_permission_id_hint' => $this->modules->nextPermissionIdHint(),
                'icon_options' => CbpModulesAdminService::iconOptions(),
                'resolver_options' => CbpModulesAdminService::targetResolverLabels(),
                'auto_assign_group_id' => CbpModulesAdminService::AUTO_ASSIGN_GROUP_ID,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $result = $this->modules->create($request->all());
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'id' => $result['id'] ?? null,
                'permission_id' => $result['permission_id'] ?? null,
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(15);

        $result = $this->modules->update($id, $request->all());
        if (! $result['ok']) {
            $status = str_contains($result['message'], 'not found') ? 404 : 422;

            return response()->json(['message' => $result['message']], $status);
        }

        return response()->json(['message' => $result['message']]);
    }
}
