<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds IT CBP module permission (Staff Portal tile + SSO gate).
 */
class ItModulePermissionSeeder
{
    public const CBP_MODULE_PERMISSION_ID = 125;

    /** @var list<array{id:int,name:string,definition:string,module:string}> */
    public const PERMISSIONS = [
        [
            'id' => self::CBP_MODULE_PERMISSION_ID,
            'name' => 'cbp_it',
            'definition' => 'CBP module access: IT',
            'module' => 'cbp',
        ],
    ];

    /**
     * @return array{permissions: int, group_links: int}
     */
    public function seed(): array
    {
        if (! Schema::hasTable('permissions')) {
            return ['permissions' => 0, 'group_links' => 0];
        }

        $perms = 0;
        foreach (self::PERMISSIONS as $row) {
            $existing = DB::table('permissions')->where('id', $row['id'])->orWhere('name', $row['name'])->first();
            if ($existing) {
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
        $adminGroup = 10;
        $links += $this->ensureGroupPermission($adminGroup, self::CBP_MODULE_PERMISSION_ID) ? 1 : 0;

        if (Schema::hasTable('group_permissions')) {
            $groupIds = DB::table('group_permissions')
                ->where('permission_id', 84)
                ->pluck('group_id')
                ->unique()
                ->all();
            foreach ($groupIds as $gid) {
                $links += $this->ensureGroupPermission((int) $gid, self::CBP_MODULE_PERMISSION_ID) ? 1 : 0;
            }
        }

        return ['permissions' => $perms, 'group_links' => $links];
    }

    private function ensureGroupPermission(int $groupId, int $permissionId): bool
    {
        if ($groupId < 1 || $permissionId < 1 || ! Schema::hasTable('group_permissions')) {
            return false;
        }
        $exists = DB::table('group_permissions')
            ->where('group_id', $groupId)
            ->where('permission_id', $permissionId)
            ->exists();
        if ($exists) {
            return false;
        }
        $row = [
            'group_id' => $groupId,
            'permission_id' => $permissionId,
        ];
        if (Schema::hasColumn('group_permissions', 'last_updated')) {
            $row['last_updated'] = now();
        }
        DB::table('group_permissions')->insert($row);

        return true;
    }
}
