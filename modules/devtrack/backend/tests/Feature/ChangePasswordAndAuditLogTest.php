<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => Hash::make('OldPassword1!'),
        ]);

        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'OldPassword1!',
                'password' => 'NewSecurePass1!',
                'password_confirmation' => 'NewSecurePass1!',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass1!', $user->password));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event_type' => 'auth_password_change',
        ]);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => Hash::make('OldPassword1!'),
        ]);

        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'WrongPassword1!',
                'password' => 'NewSecurePass1!',
                'password_confirmation' => 'NewSecurePass1!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_admin_can_list_audit_logs_and_mutations_are_recorded(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Audit Target',
                'email' => 'audit.target@example.com',
                'password' => 'SecurePass1!',
                'is_admin' => false,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertTrue(
            AuditLog::query()
                ->where('event_type', 'record_created')
                ->where('target_table', 'users')
                ->exists()
        );

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs?search=Record+audit')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs/filter-options')
            ->assertOk()
            ->assertJsonStructure(['data' => ['event_types', 'target_tables', 'http_methods']]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs?event_type=record_created&event_type_exact=1&target_table=users')
            ->assertOk()
            ->assertJsonPath('meta.total', fn ($total) => (int) $total >= 1);
    }

    public function test_admin_can_export_audit_logs_to_excel(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'actor_type' => 'system_user',
            'action' => 'Exported sample',
            'event_type' => 'auth_failed',
            'http_method' => 'POST',
            'request_uri' => 'api/v1/admin/auth/login',
            'ip_address' => '203.0.113.10',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
        ]);

        $response = $this->withToken($token)
            ->get('/api/v1/admin/audit-logs/export?event_type=auth_failed&event_type_exact=1')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.ms-excel',
            (string) $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertGreaterThan(100, strlen($response->getContent()));
        $this->assertStringContainsString('Workbook', $response->getContent());
        $this->assertStringContainsString('Failed login attempt', $response->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'audit_export',
            'user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_list_audit_logs(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->withToken($user->createToken('admin-panel')->plainTextToken)
            ->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }
}
