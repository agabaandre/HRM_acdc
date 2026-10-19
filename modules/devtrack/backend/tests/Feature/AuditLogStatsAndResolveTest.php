<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogStatsAndResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_stats_and_drill_down_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'actor_type' => 'system_user',
            'action' => 'Failed login attempt',
            'event_type' => 'auth_failed',
            'http_method' => 'POST',
            'ip_address' => '203.0.113.40',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
        ]);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'actor_type' => 'system_user',
            'action' => 'Accessed route',
            'event_type' => 'audit_repository',
            'http_method' => 'GET',
            'is_suspicious' => false,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.suspicious_open', 1)
            ->assertJsonPath('data.totals.suspicious_total', 1)
            ->assertJsonPath('data.totals.total_events', fn ($total) => (int) $total >= 2)
            ->assertJsonStructure(['data' => ['cards', 'totals', 'today']]);

        $cardKeys = collect($this->withToken($token)->getJson('/api/v1/admin/audit-logs/stats')->json('data.cards'))
            ->pluck('key')
            ->all();
        $this->assertContains('suspicious_total', $cardKeys);
        $this->assertContains('suspicious_today', $cardKeys);
        $this->assertContains('total_events', $cardKeys);

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs?suspicious=1&resolved=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.suspicious_open', true);
    }

    public function test_admin_can_resolve_single_and_bulk_suspicious_events(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $one = AuditLog::query()->create([
            'action' => 'Failed login',
            'event_type' => 'auth_failed',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
            'ip_address' => '198.51.100.10',
        ]);
        $two = AuditLog::query()->create([
            'action' => 'Failed login',
            'event_type' => 'auth_failed',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
            'ip_address' => '198.51.100.11',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/audit-logs/'.$one->id.'/resolve', [
                'note' => 'Checked — known VPN probe',
            ])
            ->assertOk()
            ->assertJsonPath('data.suspicious_resolved', true)
            ->assertJsonPath('data.suspicious_open', false);

        $one->refresh();
        $this->assertNotNull($one->suspicious_resolved_at);
        $this->assertSame($admin->id, $one->suspicious_resolved_by);
        $this->assertSame('Checked — known VPN probe', $one->suspicious_resolution_note);

        $this->withToken($token)
            ->postJson('/api/v1/admin/audit-logs/resolve-suspicious', [
                'note' => 'Bulk reviewed',
            ])
            ->assertOk()
            ->assertJsonPath('data.resolved_count', 1);

        $two->refresh();
        $this->assertNotNull($two->suspicious_resolved_at);

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.suspicious_open', 0)
            ->assertJsonPath('data.totals.suspicious_resolved', 2);
    }
}
