<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds Risk Register CBP permission + view/manage risk permissions.
 */
class RiskRegisterPermissionSeeder
{
    public const CBP_MODULE_PERMISSION_ID = 118;

    public const VIEW_DIVISION_RISKS_ID = 119;

    public const VIEW_ALL_RISKS_ID = 120;

    public const MANAGE_RISKS_ID = 121;

    /** @var list<array{id:int,name:string,definition:string,module:string}> */
    public const PERMISSIONS = [
        [
            'id' => self::CBP_MODULE_PERMISSION_ID,
            'name' => 'cbp_risk_register',
            'definition' => 'CBP module access: Risk Register',
            'module' => 'cbp',
        ],
        [
            'id' => self::VIEW_DIVISION_RISKS_ID,
            'name' => 'view_division_risks',
            'definition' => 'View risks for own division',
            'module' => 'risk',
        ],
        [
            'id' => self::VIEW_ALL_RISKS_ID,
            'name' => 'view_all_risks',
            'definition' => 'View all organisation risks',
            'module' => 'risk',
        ],
        [
            'id' => self::MANAGE_RISKS_ID,
            'name' => 'manage_risks',
            'definition' => 'Manage risks (Admin / Internal Oversight)',
            'module' => 'risk',
        ],
    ];

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
        foreach ([self::CBP_MODULE_PERMISSION_ID, self::VIEW_DIVISION_RISKS_ID, self::MANAGE_RISKS_ID] as $pid) {
            $links += $this->ensureGroupPermission($adminGroup, $pid) ? 1 : 0;
        }

        // Default staff access: view own division risks for common portal groups that already have helpdesk (93).
        if (Schema::hasTable('group_permissions')) {
            $groupIds = DB::table('group_permissions')
                ->where('permission_id', 84)
                ->pluck('group_id')
                ->unique()
                ->all();
            foreach ($groupIds as $gid) {
                $links += $this->ensureGroupPermission((int) $gid, self::VIEW_DIVISION_RISKS_ID) ? 1 : 0;
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
        DB::table('group_permissions')->insert([
            'group_id' => $groupId,
            'permission_id' => $permissionId,
            'last_updated' => now(),
        ]);

        return true;
    }
}
