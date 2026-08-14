<?php

namespace Modules\Permissions\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use App\Support\PortalReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;
use Modules\Permissions\Services\PermissionsService;

class PermissionsApiController extends Controller
{
    public function __construct(
        protected PermissionsService $permissions,
    ) {}

    /**
     * Single payload for the permissions SPA first paint (catalog + groups + initial assignments).
     */
    public function bootstrap(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        $requestedGroupId = $request->filled('group_id') ? (int) $request->query('group_id') : 0;
        $user = $request->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
        $cacheKey = PortalReadCache::key('permissions', 'bootstrap', $userId, [
            'group_id' => $requestedGroupId,
        ]);

        $payload = PortalReadCache::remember($cacheKey, function () use ($requestedGroupId): array {
            $catalog = $this->catalogPayload();
            $groups = $this->permissions->groupsWithUserCounts();
            $groupId = $requestedGroupId > 0
                ? $requestedGroupId
                : (int) ($groups[0]['id'] ?? 0);

            return [
                'catalog' => $catalog,
                'groups' => $groups,
                'selected_group_id' => $groupId > 0 ? $groupId : null,
                'permission_ids' => $groupId > 0
                    ? $this->permissions->groupPermissionIds($groupId)
                    : [],
            ];
        });

        return response()->json(['data' => $payload]);
    }

    public function catalog(): JsonResponse
    {
        PortalPermission::authorize(17);

        return response()->json([
            'data' => $this->catalogPayload(),
        ]);
    }

    public function groups(): JsonResponse
    {
        PortalPermission::authorize(17);

        return response()->json(['data' => $this->permissions->groupsWithUserCounts()]);
    }

    /**
     * @return array{permissions: list<mixed>, categories: array<string, list<mixed>>}
     */
    protected function catalogPayload(): array
    {
        return PortalReferenceCache::remember(
            PortalReferenceCache::PERMISSIONS_CATALOG_KEY,
            function (): array {
                $all = $this->permissions->permissions();

                return [
                    'permissions' => $all->values()->all(),
                    'categories' => $this->permissions->permissionsByCategory($all),
                ];
            }
        );
    }

    public function groupAssignments(int $id): JsonResponse
    {
        PortalPermission::authorize(17);

        return response()->json([
            'data' => [
                'permission_ids' => $this->permissions->groupPermissionIds($id),
            ],
        ]);
    }

    public function updateGroupAssignments(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(17);
        $validated = $request->validate([
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer',
        ]);

        $this->permissions->assignGroupPermissions($id, $validated['permission_ids'] ?? []);
        PortalReadCache::bust('permissions');

        return response()->json(['message' => 'Group permissions saved.']);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);
        $validated = $request->validate([
            'group_name' => 'required|string|min:3|max:100',
        ]);

        if (! $this->permissions->createGroup($validated['group_name'])) {
            return response()->json(['message' => 'Could not create group (name may already exist).'], 422);
        }

        PortalReadCache::bust('permissions');

        return response()->json(['message' => 'Group created.'], 201);
    }

    public function users(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);
        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $groupId = $request->filled('group_id') ? (int) $request->query('group_id') : null;

        $paginator = $this->permissions->paginateUsers(
            (string) $request->query('q', ''),
            $groupId,
            $perPage,
            $page
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function userAssignments(int $id): JsonResponse
    {
        PortalPermission::authorize(17);
        $user = DB::table('user as u')
            ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role')
            ->where('u.user_id', $id)
            ->select('u.user_id', 'u.name', 'u.role', 'ug.group_name')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $role = (int) ($user->role ?? 0);

        return response()->json([
            'data' => [
                'user' => $user,
                'permission_ids' => $this->permissions->userPermissionIds($id),
                'group_permission_count' => $role > 0 ? count($this->permissions->groupPermissionIds($role)) : 0,
            ],
        ]);
    }

    public function updateUserAssignments(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(17);
        $validated = $request->validate([
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer',
        ]);

        $this->permissions->assignUserPermissions($id, $validated['permission_ids'] ?? []);
        PortalReadCache::bust('permissions');

        return response()->json(['message' => 'User permissions saved.']);
    }

    public function copyGroupToUser(int $id): JsonResponse
    {
        PortalPermission::authorize(17);
        $this->permissions->copyGroupPermissionsToUser($id);
        PortalReadCache::bust('permissions');

        return response()->json([
            'message' => 'Group permissions copied to user.',
            'data' => [
                'permission_ids' => $this->permissions->userPermissionIds($id),
            ],
        ]);
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'definition' => 'required|string|max:255',
        ]);

        if (! $this->permissions->createPermission($validated['name'], $validated['definition'])) {
            return response()->json(['message' => 'Could not create permission (invalid or duplicate name).'], 422);
        }

        PortalReadCache::bust('permissions');

        return response()->json(['message' => 'Permission created.'], 201);
    }
}
