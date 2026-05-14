<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAgentsApiTest extends TestCase
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

    public function test_non_admin_cannot_list_agents(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 1,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);
        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/admin/agents')->assertForbidden();
    }

    public function test_admin_can_list_agents(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/v1/admin/agents')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
