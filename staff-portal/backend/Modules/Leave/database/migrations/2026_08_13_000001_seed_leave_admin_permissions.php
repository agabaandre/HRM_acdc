<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Support\LeavePermissions;

return new class extends Migration
{
    public function up(): void
    {
        foreach (LeavePermissions::catalog() as $row) {
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

        // System Administrator (10) + HR Manager (20) + HR Admin (22).
        $groupIds = [10, 20, 22];
        $assign = [
            LeavePermissions::MAKE_REQUEST,
            LeavePermissions::APPROVE_REQUEST,
            LeavePermissions::VIEW_ALL,
            LeavePermissions::MANAGE_BALANCES,
            LeavePermissions::MANAGE_SETTINGS,
        ];

        foreach ($groupIds as $groupId) {
            if (! DB::table('user_groups')->where('id', $groupId)->exists()) {
                continue;
            }

            foreach ($assign as $permissionId) {
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
        $ids = [
            LeavePermissions::VIEW_ALL,
            LeavePermissions::MANAGE_BALANCES,
            LeavePermissions::MANAGE_SETTINGS,
        ];

        DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
