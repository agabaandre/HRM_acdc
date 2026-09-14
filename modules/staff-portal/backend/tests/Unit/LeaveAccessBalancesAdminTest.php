<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Support\LeaveAccess;
use Modules\Leave\Support\LeavePermissions;
use Tests\TestCase;

class LeaveAccessBalancesAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
        DB::purge();
        DB::reconnect();
    }

    public function test_only_system_admin_and_hr_roles_are_balances_admins(): void
    {
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(17));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(21));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(23));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(24));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(null));

        $this->assertTrue(LeaveAccess::isBalancesAdminRole(10));
        $this->assertTrue(LeaveAccess::isBalancesAdminRole(20));
        $this->assertTrue(LeaveAccess::isBalancesAdminRole(22));
    }

    public function test_can_manage_balances_requires_permission_96_not_role(): void
    {
        session(['user' => ['role_id' => 10, 'permissions' => []]]);
        $this->assertFalse(LeaveAccess::canManageBalances());

        session(['user' => ['role_id' => 17, 'permissions' => [LeavePermissions::MANAGE_BALANCES]]]);
        $this->assertTrue(LeaveAccess::canManageBalances());
    }

    public function test_sync_grants_manage_balances_only_to_hr_and_admin_groups(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->text('name')->nullable();
            $table->text('definition')->nullable();
            $table->string('module', 200)->nullable();
        });
        Schema::create('user_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->text('group_name')->nullable();
        });
        Schema::create('group_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('group_id');
            $table->integer('permission_id');
            $table->timestamp('last_updated')->nullable();
        });
        Schema::create('user', function (Blueprint $table): void {
            $table->integer('user_id')->primary();
            $table->string('role');
        });
        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('permission_id');
            $table->timestamp('last_updated')->nullable();
        });

        foreach ([10, 17, 20, 22, 24] as $groupId) {
            DB::table('user_groups')->insert(['id' => $groupId, 'group_name' => (string) $groupId]);
        }

        DB::table('group_permissions')->insert([
            ['group_id' => 17, 'permission_id' => LeavePermissions::MANAGE_BALANCES, 'last_updated' => now()],
            ['group_id' => 24, 'permission_id' => LeavePermissions::MANAGE_BALANCES, 'last_updated' => now()],
            ['group_id' => 10, 'permission_id' => LeavePermissions::MAKE_REQUEST, 'last_updated' => now()],
        ]);
        DB::table('user')->insert([
            ['user_id' => 501, 'role' => '17'],
            ['user_id' => 502, 'role' => '22'],
        ]);
        DB::table('user_permissions')->insert([
            ['user_id' => 501, 'permission_id' => LeavePermissions::MANAGE_BALANCES, 'last_updated' => now()],
            ['user_id' => 502, 'permission_id' => LeavePermissions::MANAGE_BALANCES, 'last_updated' => now()],
        ]);

        LeaveAccess::syncManageBalancesPermission();

        $groupsWith96 = DB::table('group_permissions')
            ->where('permission_id', LeavePermissions::MANAGE_BALANCES)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $this->assertSame([10, 20, 22], $groupsWith96);

        $this->assertTrue(
            DB::table('group_permissions')
                ->where('group_id', 10)
                ->where('permission_id', LeavePermissions::MAKE_REQUEST)
                ->exists()
        );

        $userIdsWith96 = DB::table('user_permissions')
            ->where('permission_id', LeavePermissions::MANAGE_BALANCES)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertSame([502], $userIdsWith96);
    }
}
