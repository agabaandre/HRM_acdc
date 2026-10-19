<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockedIpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_admin_can_block_ip_with_reason_and_requests_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/api/v1/admin/blocked-ips', [
                'ip_address' => '203.0.113.50',
                'reason' => 'Repeated failed logins from audit log',
            ])
            ->assertCreated()
            ->assertJsonPath('data.ip.ip_address', '203.0.113.50')
            ->assertJsonPath('data.ip.reason', 'Repeated failed logins from audit log');

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '203.0.113.50',
            'is_active' => 1,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'anyone@example.com',
                'password' => 'Whatever1!',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied from this IP address.');
    }

    public function test_cannot_block_own_current_ip(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/admin/blocked-ips', [
                'ip_address' => '198.51.100.20',
                'reason' => 'Should not lock myself out',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot block your own current IP address.');
    }

    public function test_block_all_suspicious_ips_uses_reason(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        AuditLog::query()->create([
            'action' => 'Failed login',
            'event_type' => 'auth_failed',
            'ip_address' => '203.0.113.10',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
        ]);
        AuditLog::query()->create([
            'action' => 'Failed login',
            'event_type' => 'auth_failed',
            'ip_address' => '203.0.113.11',
            'is_suspicious' => true,
            'suspicious_reasons' => 'Failed login attempt',
        ]);
        AuditLog::query()->create([
            'action' => 'Login',
            'event_type' => 'auth_success',
            'ip_address' => '203.0.113.99',
            'is_suspicious' => false,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/v1/admin/blocked-ips/block-suspicious', [
                'reason' => 'Bulk block from audit review',
            ])
            ->assertOk()
            ->assertJsonPath('data.ips.count', 2);

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '203.0.113.10',
            'reason' => 'Bulk block from audit review',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '203.0.113.11',
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('blocked_ips', [
            'ip_address' => '203.0.113.99',
        ]);
    }

    public function test_admin_can_list_and_unblock_ips(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $blocked = BlockedIp::query()->create([
            'ip_address' => '203.0.113.77',
            'reason' => 'Manual block',
            'blocked_by' => $admin->id,
            'is_active' => true,
        ]);

        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/blocked-ips')
            ->assertOk()
            ->assertJsonPath('data.0.ip_address', '203.0.113.77');

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/blocked-ips/'.$blocked->id)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_health_endpoint_remains_reachable_from_blocked_ip(): void
    {
        BlockedIp::query()->create([
            'ip_address' => '203.0.113.88',
            'reason' => 'Probe',
            'is_active' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.88'])
            ->getJson('/api/v1/health');

        $this->assertNotSame(403, $response->status());
        $this->assertTrue(in_array($response->status(), [200, 503], true));
        $response->assertJsonMissing(['message' => 'Access denied from this IP address.']);
    }

    public function test_admin_can_block_email_only_and_login_is_rejected(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'is_admin' => true,
            'is_active' => true,
        ]);
        User::factory()->create([
            'email' => 'attacker@example.com',
            'password' => bcrypt('CorrectPass1!'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->postJson('/api/v1/admin/blocked-ips', [
                'scope' => 'email',
                'email' => 'attacker@example.com',
                'reason' => 'Suspicious login attempts',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email.email', 'attacker@example.com');

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'attacker@example.com',
            'password' => 'CorrectPass1!',
        ])->assertStatus(422);
    }

    public function test_admin_can_block_ip_and_email_together(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->withToken($admin->createToken('admin-panel')->plainTextToken)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/api/v1/admin/blocked-ips', [
                'scope' => 'both',
                'ip_address' => '203.0.113.60',
                'email' => 'bad.actor@example.com',
                'reason' => 'Block IP and email from audit log',
            ])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'both')
            ->assertJsonPath('data.ip.ip_address', '203.0.113.60')
            ->assertJsonPath('data.email.email', 'bad.actor@example.com');

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '203.0.113.60', 'is_active' => 1]);
        $this->assertDatabaseHas('blocked_emails', ['email' => 'bad.actor@example.com', 'is_active' => 1]);
    }
}
