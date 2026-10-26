<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Performance\Support\PerformancePermissions;

/**
 * Seeds performance permissions and assigns change-supervisors to Admin + HR roles.
 */
class PerformancePermissionSeeder
{
    /**
     * @return array{permissions: int, group_links: int}
     */
    public function seed(): array
    {
        if (! Schema::hasTable('permissions')) {
            return ['permissions' => 0, 'group_links' => 0];
        }

        $perms = 0;
        foreach (PerformancePermissions::catalog() as $row) {
            $existing = DB::table('permissions')->where('id', $row['id'])->first()
                ?? DB::table('permissions')->where('name', $row['name'])->first();

            if ($existing) {
                $update = [
                    'name' => $row['name'],
                    'definition' => $row['definition'],
                ];
                if (Schema::hasColumn('permissions', 'module')) {
                    $update['module'] = $row['module'];
                }
                DB::table('permissions')->where('id', $existing->id)->update($update);

                continue;
            }

            $insert = [
                'id' => $row['id'],
                'name' => $row['name'],
                'definition' => $row['definition'],
            ];
            if (Schema::hasColumn('permissions', 'module')) {
                $insert['module'] = $row['module'];
            }
            DB::table('permissions')->insert($insert);
            $perms++;
        }

        $links = 0;
        foreach ($this->targetGroupIds() as $groupId) {
            if ($this->ensureGroupPermission($groupId, PerformancePermissions::CHANGE_SUPERVISORS)) {
                $links++;
            }
        }

        return ['permissions' => $perms, 'group_links' => $links];
    }

    /**
     * System Administrator + every HR role (known ids and name matches).
     *
     * @return list<int>
     */
    protected function targetGroupIds(): array
    {
        $ids = PerformancePermissions::defaultChangeSupervisorRoleIds();

        if (Schema::hasTable('user_groups')) {
            $namedIds = DB::table('user_groups')
                ->where(function ($q): void {
                    $q->where('group_name', 'like', '%HR%')
                        ->orWhere('group_name', 'like', '%Human Resource%')
                        ->orWhere('group_name', 'System Administrator')
                        ->orWhere('group_name', 'Admin')
                        ->orWhere('group_name', 'like', 'Administrator%');
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $namedIds);
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    protected function ensureGroupPermission(int $groupId, int $permissionId): bool
    {
        if ($groupId < 1 || $permissionId < 1 || ! Schema::hasTable('group_permissions')) {
            return false;
        }

        if (Schema::hasTable('user_groups') && ! DB::table('user_groups')->where('id', $groupId)->exists()) {
            return false;
        }

        $exists = DB::table('group_permissions')
            ->where('group_id', $groupId)
            ->where('permission_id', $permissionId)
            ->exists();
        if ($exists) {
            return false;
        }

        DB::table('group_permissions')->insert([
            'group_id' => $groupId,
            'permission_id' => $permissionId,
            'last_updated' => now(),
        ]);

        return true;
    }
}
