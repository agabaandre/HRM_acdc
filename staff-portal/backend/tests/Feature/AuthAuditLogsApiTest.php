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
            ],
            [
                'user_id' => 11,
                'auth_staff_id' => 101,
                'name' => 'Bob Viewer',
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
