<?php

namespace Modules\Permissions\Services;

use App\Support\PortalReferenceCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;

class PermissionsService
{
    /**
     * @return Collection<int, object>
     */
    public function groups(): Collection
    {
        return DB::table('user_groups')->orderBy('group_name')->get();
    }

    /**
     * Groups with user counts in one query (avoids N+1 groupUserCount).
     *
     * @return list<array{id: int, group_name: string, user_count: int}>
     */
    public function groupsWithUserCounts(): array
    {
        $counts = DB::table('user')
            ->selectRaw('role, COUNT(*) as c')
            ->whereNotNull('role')
            ->groupBy('role')
            ->pluck('c', 'role');

        return $this->groups()->map(fn ($g) => [
            'id' => (int) $g->id,
            'group_name' => (string) $g->group_name,
            'user_count' => (int) ($counts[$g->id] ?? 0),
        ])->values()->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function permissions(): Collection
    {
        return DB::table('permissions')->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    public function groupPermissionIds(int $groupId): array
    {
        return DB::table('group_permissions')
            ->where('group_id', $groupId)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function userPermissionIds(int $userId): array
    {
        return DB::table('user_permissions')
            ->where('user_id', $userId)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $permissionIds
     */
    public function assignGroupPermissions(int $groupId, array $permissionIds): void
    {
        DB::table('group_permissions')->where('group_id', $groupId)->delete();

        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            if ($pid > 0) {
                $rows[] = [
                    'group_id' => $groupId,
                    'permission_id' => $pid,
                    'last_updated' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('group_permissions')->insert($rows);
        }
    }

    /**
     * @param  list<int|string>  $permissionIds
     */
    public function assignUserPermissions(int $userId, array $permissionIds): void
    {
        DB::table('user_permissions')->where('user_id', $userId)->delete();

        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            if ($pid > 0) {
                $rows[] = [
                    'user_id' => $userId,
                    'permission_id' => $pid,
                    'last_updated' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }
    }

    public function copyGroupPermissionsToUser(int $userId): void
    {
        $role = DB::table('user')->where('user_id', $userId)->value('role');
        if ($role === null) {
            return;
        }

        $this->assignUserPermissions($userId, $this->groupPermissionIds((int) $role));
    }

    public function createGroup(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 3) {
            return false;
        }

        if (DB::table('user_groups')->where('group_name', $name)->exists()) {
            return false;
        }

        return DB::table('user_groups')->insert(['group_name' => $name]);
    }

    public function createPermission(string $name, string $definition): bool
    {
        $name = strtolower(preg_replace('/\s+/', '', trim($name)));
        $definition = ucwords(trim($definition));

        if ($name === '' || $definition === '' || ! preg_match('/^[a-zA-Z_]+$/', $name)) {
            return false;
        }

        if (DB::table('permissions')->where('name', $name)->exists()) {
            return false;
        }

        $module = str_contains($name, 'leave')
            ? 'leave'
            : strtolower((string) (explode('_', $name)[0] ?? 'general'));

        $ok = DB::table('permissions')->insert([
            'name' => $name,
            'definition' => $definition,
            'module' => $module,
        ]);
        if ($ok) {
            PortalReferenceCache::bustPermissionsCatalog();
        }

        return $ok;
    }

    public function groupUserCount(int $groupId): int
    {
        return (int) DB::table('user')->where('role', $groupId)->count();
    }

    public function paginateUsers(
        string $search = '',
        ?int $groupId = null,
        int $perPage = 20,
        ?int $page = null
    ): LengthAwarePaginator {
        // Subquery avoids GROUP BY + DISTINCT count bug (was returning total=1).
        $customCounts = DB::table('user_permissions')
            ->selectRaw('user_id, COUNT(DISTINCT permission_id) as custom_permission_count')
            ->groupBy('user_id');

        $q = DB::table('user as u')
            ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role')
            ->leftJoinSub($customCounts, 'upc', 'upc.user_id', '=', 'u.user_id')
            ->select(
                'u.user_id',
                'u.name',
                'u.role',
                'ug.group_name',
                DB::raw('COALESCE(upc.custom_permission_count, 0) as custom_permission_count')
            )
            ->orderBy('u.name');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term): void {
                $w->where('u.name', 'like', $term)
                    ->orWhere('u.user_id', 'like', $term);
            });
        }

        if ($groupId) {
            $q->where('u.role', $groupId);
        }

        return PortalTable::paginateDistinct($q, 'u.user_id', $perPage, $page);
    }

    /**
     * @param  Collection<int, object>  $permissions
     * @return array<string, list<object>>
     */
    public function permissionsByCategory(Collection $permissions): array
    {
        $categories = [];
        foreach ($permissions as $perm) {
            $module = trim((string) ($perm->module ?? ''));
            if ($module !== '') {
                $category = ucfirst(strtolower($module));
            } else {
                $category = ucfirst(explode('_', (string) $perm->name)[0] ?? 'General');
            }
            $categories[$category][] = $perm;
        }
        ksort($categories);

        return $categories;
    }
}
