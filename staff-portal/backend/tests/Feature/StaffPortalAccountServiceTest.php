<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Staff\Services\StaffPortalAccountService;
use Tests\TestCase;

class StaffPortalAccountServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        $this->createTables();
    }

    public function test_sync_updates_stale_user_name_from_staff(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 42,
            'fname' => 'Habtamu',
            'lname' => 'Anteneh',
            'work_email' => 'habtamu@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 42,
            'status_id' => 1,
        ]);
        DB::table('user')->insert([
            'auth_staff_id' => 42,
            'name' => 'Andriamampiandrasoa Jean Christian',
            'role' => 17,
            'status' => 1,
        ]);

        $result = app(StaffPortalAccountService::class)->syncForStaff(42);

        $this->assertTrue($result['changed']);
        $this->assertSame('updated_name', $result['action']);
        $this->assertSame('Habtamu Anteneh', DB::table('user')->where('auth_staff_id', 42)->value('name'));
    }

    public function test_sync_leaves_matching_name_unchanged(): void
    {
        DB::table('staff')->insert([
            'staff_id' => 43,
            'fname' => 'Ada',
            'lname' => 'Okeke',
            'work_email' => 'ada@example.test',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 43,
            'status_id' => 1,
        ]);
        DB::table('user')->insert([
            'auth_staff_id' => 43,
            'name' => 'Ada Okeke',
            'role' => 17,
            'status' => 1,
        ]);

        $result = app(StaffPortalAccountService::class)->syncForStaff(43);

        $this->assertFalse($result['changed']);
        $this->assertSame('already_active', $result['action']);
        $this->assertSame('Ada Okeke', DB::table('user')->where('auth_staff_id', 43)->value('name'));
    }

    private function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->increments('staff_id');
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
        });
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->integer('staff_id');
            $table->integer('status_id')->nullable();
        });
        Schema::create('user', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->integer('auth_staff_id')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(0);
        });
        Schema::create('setting', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('default_password')->nullable();
        });
    }
}
