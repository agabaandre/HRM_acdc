<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds Staff Portal permission to manage the Finance Funding Portfolio workbook.
 */
class FundingPortfolioPermissionSeeder
{
    public const MANAGE_FUNDING_PORTFOLIO_ID = 124;

    public const FINANCE_MODULE_ID = 92;

    /** @var list<array{id:int,name:string,definition:string,module:string}> */
    public const PERMISSIONS = [
        [
            'id' => self::MANAGE_FUNDING_PORTFOLIO_ID,
            'name' => 'manage_funding_portfolio',
            'definition' => 'Manage Funding Portfolio data entry (extramural and related sections)',
            'module' => 'finance',
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
            $existingById = DB::table('permissions')->where('id', $row['id'])->first();
            if ($existingById) {
                $update = [
                    'name' => $row['name'],
                    'definition' => $row['definition'],
                ];
                if (Schema::hasColumn('permissions', 'module')) {
                    $update['module'] = $row['module'];
                }
                DB::table('permissions')->where('id', $row['id'])->update($update);

                continue;
            }

            $existingByName = DB::table('permissions')->where('name', $row['name'])->first();
            if ($existingByName) {
                $update = ['definition' => $row['definition']];
                if (Schema::hasColumn('permissions', 'module')) {
                    $update['module'] = $row['module'];
                }
                DB::table('permissions')->where('name', $row['name'])->update($update);

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
        $groupIds = [10]; // System Administrator
        if (Schema::hasTable('group_permissions')) {
            $financeGroups = DB::table('group_permissions')
                ->where('permission_id', self::FINANCE_MODULE_ID)
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
            $groupIds = array_values(array_unique(array_merge($groupIds, $financeGroups)));
        }

        foreach ($groupIds as $gid) {
            $links += $this->ensureGroupPermission((int) $gid, self::MANAGE_FUNDING_PORTFOLIO_ID) ? 1 : 0;
        }

        return ['permissions' => $perms, 'group_links' => $links];
    }

    private function ensureGroupPermission(int $groupId, int $permissionId): bool
    {
        if ($groupId < 1 || $permissionId < 1 || ! Schema::hasTable('group_permissions')) {
            return false;
        }
        if (! Schema::hasTable('user_groups') || ! DB::table('user_groups')->where('id', $groupId)->exists()) {
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
