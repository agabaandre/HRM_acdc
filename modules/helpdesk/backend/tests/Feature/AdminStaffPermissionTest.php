<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900001,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_grant_helpdesk_admin_allows_settings_without_portal_role_10(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);

        $agent = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $agent->id,
            'staff_id' => 42,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_portal_role' => 5,
            'grant_helpdesk_admin' => true,
            'is_designated_agent' => true,
            'synced_at' => now(),
        ]);

        Sanctum::actingAs($agent->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/admin/settings')
            ->assertOk();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.profile.is_helpdesk_admin', true)
            ->assertJsonPath('user.profile.role', 'agent');
    }

    public function test_admin_can_update_staff_permission_overrides(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $staff = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $staff->id,
            'staff_id' => 77,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_portal_role' => 3,
            'synced_at' => now(),
        ]);

        $this->putJson('/api/v1/admin/staff-permissions/'.$staff->id, [
            'grant_helpdesk_admin' => true,
            'grant_supervisor_access' => false,
            'can_manage_kb' => true,
            'can_reassign_tickets' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.grant_helpdesk_admin', true)
            ->assertJsonPath('data.can_manage_kb', true);

        $profile = $staff->fresh(['helpdeskProfile'])->helpdeskProfile;
        $this->assertTrue($profile->grant_helpdesk_admin);
        $this->assertTrue($profile->isHelpdeskAdmin());
    }

    public function test_staff_permissions_index_excludes_agents(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $agent = User::factory()->create(['name' => 'Agent User']);
        HelpdeskProfile::query()->create([
            'user_id' => $agent->id,
            'staff_id' => 1,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        $user = User::factory()->create(['name' => 'Plain User']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 2,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/staff-permissions')->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Plain User', $names);
        $this->assertNotContains('Agent User', $names);
    }
}
