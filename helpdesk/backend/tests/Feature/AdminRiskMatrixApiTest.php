<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskRiskMatrixEntry;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRiskMatrixApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900010,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_admin_can_manage_risk_matrix_entries(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $this->seedHelpdeskStaffDirectoryCache(88001, 'vip@example.org', 'VIP', 'Leader');

        $create = $this->postJson('/api/v1/admin/risk-matrix', [
            'staff_id' => 88001,
            'priority' => 'critical',
            'notes' => 'Executive escalation',
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.staff_id', 88001);

        $id = (int) $create->json('data.id');

        $this->getJson('/api/v1/admin/risk-matrix')
            ->assertOk()
            ->assertJsonPath('data.0.staff_name', 'VIP Leader')
            ->assertJsonPath('meta.summary.active', 1);

        $this->putJson("/api/v1/admin/risk-matrix/{$id}", [
            'priority' => 'high',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/admin/risk-matrix/{$id}")->assertOk();
        $this->assertDatabaseMissing('helpdesk_risk_matrix_entries', ['id' => $id]);
    }

    public function test_admin_can_bulk_create_priority_matrix_entries(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $this->seedHelpdeskStaffDirectoryCache(88010, 'a@example.org', 'Alpha', 'One');

        $category = HelpdeskCategory::query()->firstOrFail();

        $this->postJson('/api/v1/admin/risk-matrix/bulk', [
            'staff_ids' => [88010],
            'category_ids' => [0, $category->id],
            'priority' => 'high',
            'notes' => 'Bulk test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseCount('helpdesk_risk_matrix_entries', 2);
    }

    public function test_duplicate_global_entry_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser());
        $this->seedHelpdeskStaffDirectoryCache(88002, 'dup@example.org', 'Dup', 'User');

        HelpdeskRiskMatrixEntry::query()->create([
            'staff_id' => 88002,
            'priority' => 'high',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/risk-matrix', [
            'staff_id' => 88002,
            'priority' => 'critical',
        ])->assertStatus(422);
    }
}
