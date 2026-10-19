<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuspiciousAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.captcha.enabled' => false]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_repeated_failed_logins_from_same_ip_are_flagged_suspicious(): void
    {
        config([
            'services.audit_suspicious.auth_fail_ip_threshold' => 3,
            'services.audit_suspicious.window_minutes' => 15,
        ]);

        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/admin/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'WrongPassword1!',
            ])->assertStatus(422);
        }

        $suspicious = AuditLog::query()
            ->where('event_type', 'auth_failed')
            ->where('is_suspicious', true)
            ->get();

        $this->assertGreaterThanOrEqual(1, $suspicious->count());
        $this->assertStringContainsString('Multiple failed logins from IP', (string) $suspicious->last()->suspicious_reasons);

        $this->withToken(
            User::factory()->create(['is_admin' => true, 'is_active' => true])
                ->createToken('admin-panel')
                ->plainTextToken
        )
            ->getJson('/api/v1/admin/audit-logs?suspicious=1')
            ->assertOk()
            ->assertJsonPath('meta.total', fn ($total) => (int) $total >= 1);
    }

    public function test_deactivated_account_login_is_flagged_suspicious(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_admin' => true,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'CorrectPass1!',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth_failed_inactive',
            'is_suspicious' => 1,
        ]);
    }

    public function test_user_delete_is_flagged_suspicious(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $target = User::factory()->create([
            'is_admin' => false,
            'is_active' => false,
            'approval_status' => \App\Enums\UserApprovalStatus::Rejected,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->deleteJson('/api/v1/admin/users/'.$target->id)
            ->assertOk();

        $log = AuditLog::query()
            ->where('event_type', 'record_deleted')
            ->where('target_table', 'users')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_suspicious);
        $this->assertStringContainsString('User account deleted', (string) $log->suspicious_reasons);
    }

    public function test_single_failed_login_is_still_flagged_suspicious(): void
    {
        app(AuditLogService::class)->log('Failed login attempt', [
            'event_type' => 'auth_failed',
            'http_method' => 'POST',
            'attempted_email' => 'once@example.com',
            'new_values' => ['email' => 'once@example.com'],
        ]);

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_suspicious);
        $this->assertStringContainsString('Failed login attempt', (string) $log->suspicious_reasons);
    }
}
