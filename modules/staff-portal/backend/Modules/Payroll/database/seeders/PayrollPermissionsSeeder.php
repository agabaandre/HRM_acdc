<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Support\PayrollPermissions;

class PayrollPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertCatalog();
        $this->assignToGroups([10], PayrollPermissions::adminAllIds());
        $this->assignToGroups([20, 22], PayrollPermissions::hrIds());
    }

    private function upsertCatalog(): void
    {
        foreach (PayrollPermissions::catalog() as $row) {
            $existingById = DB::table('permissions')->where('id', $row['id'])->first();
            if ($existingById) {
                DB::table('permissions')->where('id', $row['id'])->update([
                    'name' => $row['name'],
                    'definition' => $row['definition'],
                    'module' => $row['module'],
                ]);

                continue;
            }

            $existingByName = DB::table('permissions')->where('name', $row['name'])->first();
            if ($existingByName) {
                DB::table('permissions')->where('name', $row['name'])->update([
                    'definition' => $row['definition'],
                    'module' => $row['module'],
                ]);

                continue;
            }

            DB::table('permissions')->insert([
                'id' => $row['id'],
                'name' => $row['name'],
                'definition' => $row['definition'],
                'module' => $row['module'],
            ]);
        }
    }

    /**
     * @param  list<int>  $groupIds
     * @param  list<int>  $permissionIds
     */
    private function assignToGroups(array $groupIds, array $permissionIds): void
    {
        foreach ($groupIds as $groupId) {
            if (! DB::table('user_groups')->where('id', $groupId)->exists()) {
                $this->command?->warn("user_groups.id={$groupId} not found; skipped payroll permission assignment.");

                continue;
            }

            foreach ($permissionIds as $permissionId) {
                $exists = DB::table('group_permissions')
                    ->where('group_id', $groupId)
                    ->where('permission_id', $permissionId)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('group_permissions')->insert([
                    'group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'last_updated' => now(),
                ]);
            }
        }
    }
}
