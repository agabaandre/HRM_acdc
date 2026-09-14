<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Support\PayrollPermissions;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PayrollPermissions::catalog() as $row) {
            $existing = DB::table('permissions')->where('id', $row['id'])->first();
            if ($existing) {
                DB::table('permissions')->where('id', $row['id'])->update([
                    'name' => $row['name'],
                    'definition' => $row['definition'],
                    'module' => $row['module'],
                ]);

                continue;
            }

            if (DB::table('permissions')->where('name', $row['name'])->exists()) {
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

        $assignAdmin = PayrollPermissions::adminAllIds();
        $assignHr = PayrollPermissions::hrIds();

        foreach ([10 => $assignAdmin, 20 => $assignHr, 22 => $assignHr] as $groupId => $ids) {
            if (! DB::table('user_groups')->where('id', $groupId)->exists()) {
                continue;
            }

            foreach ($ids as $permissionId) {
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

    public function down(): void
    {
        $ids = PayrollPermissions::moduleAccessIds();
        DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
