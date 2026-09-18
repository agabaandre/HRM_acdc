<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Http\Controllers\Api\V1\AuthAdminApiController;
use Tests\TestCase;

class AuthAuditLogsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session()->put('user.permissions', [17]);
        session()->put('user.user_id', 99);

        $this->createTables();
        $this->seedFixtures();
    }

    public function test_audit_logs_applies_joined_filters_and_keeps_q_as_search_alias(): void
    {
        $this->insertLog([
            'id' => 1,
            'user_id' => 10,
            'action' => 'Updated policy assignment',
            'http_method' => 'POST',
            'event_type' => 'policy_update',
            'request_uri' => '/api/v1/policies/42',
            'target_table' => 'policies',
            'target_id' => '42',
            'created_at' => '2026-08-10 08:00:00',
        ]);
        $this->insertLog([
            'id' => 2,
            'user_id' => 11,
            'action' => 'Viewed dashboard',
            'http_method' => 'GET',
            'event_type' => 'page_view',
            'request_uri' => '/dashboard',
            'target_table' => null,
            'target_id' => null,
            'created_at' => '2026-08-10 09:00:00',
        ]);
        $this->insertLog([
            'id' => 3,
            'user_id' => 10,
            'action' => 'Updated policy assignment',
            'http_method' => 'POST',
            'event_type' => 'policy_update',
            'request_uri' => '/api/v1/policies/99',
            'target_table' => 'policies',
            'target_id' => '99',
            'created_at' => '2026-08-12 08:00:00',
        ]);

        $response = app(AuthAdminApiController::class)->auditLogs(
            Request::create('/api/v1/auth/audit-logs', 'GET', [
                'q' => 'policy',
                'name' => 'Alice',
                'email' => 'alice.audit@example.test',
                'http_method' => 'post',
                'event_type' => 'policy_update',
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-10',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame(1, (int) $payload['data'][0]['id']);
        $this->assertSame('Alice Audit', $payload['data'][0]['user_name']);
        $this->assertSame('alice.audit@example.test', $payload['data'][0]['user_email']);
        $this->assertSame(1, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['current_page']);
        $this->assertSame(1, $payload['meta']['last_page']);
        $this->assertSame(50, $payload['meta']['per_page']);
        $this->assertTrue($payload['meta']['extended']);
    }

    public function test_audit_logs_uses_search_param_for_pagination_meta(): void
    {
        foreach (range(1, 3) as $index) {
            $this->insertLog([
                'id' => $index,
                'user_id' => 10,
                'action' => "Created role {$index}",
                'http_method' => 'POST',
                'event_type' => 'role_create',
                'request_uri' => "/api/v1/roles/{$index}",
                'target_table' => 'roles',
                'target_id' => (string) $index,
                'created_at' => sprintf('2026-08-11 0%d:00:00', $index),
            ]);
        }

        $response = app(AuthAdminApiController::class)->auditLogs(
            Request::create('/api/v1/auth/audit-logs', 'GET', [
                'search' => 'Created role',
                'page' => 2,
                'per_page' => 2,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame(1, (int) $payload['data'][0]['id']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertSame(2, $payload['meta']['current_page']);
        $this->assertSame(2, $payload['meta']['last_page']);
        $this->assertSame(2, $payload['meta']['per_page']);
        $this->assertTrue($payload['meta']['extended']);
    }

    public function test_revert_audit_log_restores_whitelisted_user_fields_and_marks_log_reverted(): void
    {
        DB::table('user')->where('user_id', 10)->update([
            'name' => 'Changed Name',
            'role' => 5,
            'status' => 0,
            'allow_email_login' => 0,
        ]);

        $this->insertLog([
            'id' => 50,
            'user_id' => 11,
            'action' => 'Updated user profile',
            'http_method' => 'PUT',
            'event_type' => 'record_update',
            'request_uri' => '/api/v1/auth/users/10',
            'target_table' => 'user',
            'target_id' => '10',
            'old_values' => json_encode([
                'name' => 'Alice Audit',
                'role' => 17,
                'status' => 1,
                'allow_email_login' => 1,
                'password' => 'should-not-be-restored',
            ], JSON_THROW_ON_ERROR),
            'new_values' => json_encode([
                'name' => 'Changed Name',
                'role' => 5,
                'status' => 0,
                'allow_email_login' => 0,
            ], JSON_THROW_ON_ERROR),
            'created_at' => '2026-08-12 10:00:00',
        ]);

        $response = app(AuthAdminApiController::class)->revertAuditLog(
            50,
            Request::create('/api/v1/auth/audit-logs/50/revert', 'POST')
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['ok']);
        $this->assertSame('Changes reverted from audit snapshot.', $payload['message']);
        $this->assertSame('Alice Audit', DB::table('user')->where('user_id', 10)->value('name'));
        $this->assertSame(17, (int) DB::table('user')->where('user_id', 10)->value('role'));
        $this->assertSame(1, (int) DB::table('user')->where('user_id', 10)->value('status'));
        $this->assertSame(1, (int) DB::table('user')->where('user_id', 10)->value('allow_email_login'));
        $this->assertSame(99, (int) DB::table('user_logs')->where('id', 50)->value('reverted_by_user_id'));
        $this->assertNotNull(DB::table('user_logs')->where('id', 50)->value('reverted_at'));
    }

    public function test_revert_audit_log_returns_422_when_old_values_are_missing(): void
    {
        DB::table('user')->where('user_id', 10)->update([
            'name' => 'Changed Name',
            'role' => 5,
            'status' => 0,
            'allow_email_login' => 0,
        ]);

        $this->insertLog([
            'id' => 51,
            'user_id' => 11,
            'action' => 'Updated user profile',
            'http_method' => 'PUT',
            'event_type' => 'record_update',
            'request_uri' => '/api/v1/auth/users/10',
            'target_table' => 'user',
            'target_id' => '10',
            'old_values' => null,
            'created_at' => '2026-08-12 10:30:00',
        ]);

        $response = app(AuthAdminApiController::class)->revertAuditLog(
            51,
            Request::create('/api/v1/auth/audit-logs/51/revert', 'POST')
        );

        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['ok']);
        $this->assertSame('No old_values snapshot is available to restore.', $payload['message']);
        $this->assertSame('Changed Name', DB::table('user')->where('user_id', 10)->value('name'));
        $this->assertNull(DB::table('user_logs')->where('id', 51)->value('reverted_at'));
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable();
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->integer('auth_staff_id')->nullable();
            $table->string('name')->nullable();
            $table->integer('role')->nullable();
            $table->integer('status')->default(0);
            $table->integer('allow_email_login')->default(0);
        });

        Schema::create('user_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('http_method')->nullable();
            $table->string('event_type')->nullable();
            $table->string('request_uri')->nullable();
            $table->string('target_table')->nullable();
            $table->string('target_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->integer('reverted_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            [
                'staff_id' => 100,
                'fname' => 'Alice',
                'lname' => 'Audit',
                'work_email' => 'alice.audit@example.test',
            ],
            [
                'staff_id' => 101,
                'fname' => 'Bob',
                'lname' => 'Viewer',
                'work_email' => 'bob.viewer@example.test',
            ],
        ]);

        DB::table('user')->insert([
            [
                'user_id' => 10,
                'auth_staff_id' => 100,
                'name' => 'Alice Audit',
                'role' => 17,
                'status' => 1,
                'allow_email_login' => 1,
            ],
            [
                'user_id' => 11,
                'auth_staff_id' => 101,
                'name' => 'Bob Viewer',
                'role' => 18,
                'status' => 1,
                'allow_email_login' => 0,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertLog(array $attributes): void
    {
        DB::table('user_logs')->insert($attributes);
    }
}
