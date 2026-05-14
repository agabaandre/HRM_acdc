<?php

namespace Tests\Feature;

use App\Models\HelpdeskAuditLog;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900011,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $admin = $this->adminUser();

        HelpdeskAuditLog::query()->create([
            'user_id' => $admin->id,
            'staff_id' => 900011,
            'action' => 'test.seed',
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'correlation_id' => null,
            'old_values' => null,
            'new_values' => ['note' => 'seed'],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'test.seed');
    }
}
