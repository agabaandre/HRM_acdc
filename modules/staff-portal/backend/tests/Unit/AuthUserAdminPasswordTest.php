<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Services\AuthUserAdminService;
use Modules\Audit\Services\AuditLogService;
use Modules\Staff\Services\StaffPortalAccountService;
use Tests\TestCase;

class AuthUserAdminPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('work_email')->nullable();
            $table->string('tel_1')->nullable();
            $table->string('tel_2')->nullable();
            $table->string('photo')->nullable();
            $table->string('title')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('oname')->nullable();
            $table->string('SAPNO')->nullable();
        });

        Schema::create('user_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('group_name');
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->integer('user_id')->primary();
            $table->string('name')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(1);
            $table->string('password')->nullable();
            $table->integer('auth_staff_id')->nullable();
            $table->integer('allow_email_login')->default(0);
            $table->integer('isChanged')->default(0);
        });

        DB::table('user_groups')->insert(['id' => 1, 'group_name' => 'Admin']);
        DB::table('user')->insert([
            'user_id' => 10,
            'name' => 'Ada',
            'role' => 1,
            'status' => 1,
            'password' => password_hash('old-pass', PASSWORD_BCRYPT),
            'auth_staff_id' => null,
            'allow_email_login' => 1,
            'isChanged' => 1,
        ]);
    }

    public function test_generate_random_password_has_mixed_character_classes(): void
    {
        $service = $this->makeService();
        $password = $service->generateRandomPassword(12);

        $this->assertSame(12, strlen($password));
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[!@#$%\*\?]/', $password);
    }

    public function test_reset_password_returns_plain_random_password(): void
    {
        $service = $this->makeService();
        $result = $service->resetPassword(10);

        $this->assertArrayHasKey('password', $result);
        $this->assertNotSame('', $result['password']);
        $this->assertTrue(password_verify(
            $result['password'],
            (string) DB::table('user')->where('user_id', 10)->value('password')
        ));
    }

    public function test_update_can_generate_temporary_password(): void
    {
        $service = $this->makeService();
        $row = $service->update(10, [
            'name' => 'Ada Lovelace',
            'change_password' => true,
        ]);

        $this->assertSame('Ada Lovelace', $row['name']);
        $this->assertNotEmpty($row['temporary_password'] ?? null);
        $this->assertTrue(password_verify(
            (string) $row['temporary_password'],
            (string) DB::table('user')->where('user_id', 10)->value('password')
        ));
    }

    protected function makeService(): AuthUserAdminService
    {
        return new AuthUserAdminService(
            $this->createMock(AuditLogService::class),
            $this->createMock(StaffPortalAccountService::class),
        );
    }
}
