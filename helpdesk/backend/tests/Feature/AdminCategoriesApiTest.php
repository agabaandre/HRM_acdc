<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoriesApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900002,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_admin_can_create_and_update_category(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Test Category',
            'sort_order' => 99,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Test Category');

        $cat = HelpdeskCategory::query()->where('name', 'Test Category')->firstOrFail();

        $this->putJson('/api/v1/admin/categories/'.$cat->id, [
            'name' => 'Test Category Renamed',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);
    }
}
