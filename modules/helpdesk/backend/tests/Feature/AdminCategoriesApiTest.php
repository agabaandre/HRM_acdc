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
            'default_priority' => 'high',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Test Category')
            ->assertJsonPath('data.default_priority', 'high');

        $cat = HelpdeskCategory::query()->where('name', 'Test Category')->firstOrFail();

        $this->putJson('/api/v1/admin/categories/'.$cat->id, [
            'name' => 'Test Category Renamed',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_remap_category_and_move_tickets(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $buId = (int) \App\Models\HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->value('id');
        $source = HelpdeskCategory::query()->create([
            'name' => 'Remap Source',
            'slug' => 'remap-source',
            'sort_order' => 900,
            'is_active' => true,
            'default_priority' => 'medium',
            'business_unit_id' => $buId,
        ]);
        $target = HelpdeskCategory::query()->create([
            'name' => 'Remap Target',
            'slug' => 'remap-target',
            'sort_order' => 901,
            'is_active' => true,
            'default_priority' => 'medium',
            'business_unit_id' => $buId,
        ]);

        $ticket = \App\Models\HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-REMAP-1',
            'category_id' => $source->id,
            'business_unit_id' => $buId,
            'subject' => 'Remap me',
            'description' => 'Body',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'portal',
            'requester_name' => 'Tester',
            'requester_email' => 'tester@example.org',
        ]);

        $this->postJson('/api/v1/admin/categories/'.$source->id.'/remap', [
            'target_category_id' => $target->id,
        ])->assertOk();

        $this->assertDatabaseMissing('helpdesk_categories', ['id' => $source->id]);
        $this->assertSame($target->id, (int) $ticket->fresh()->category_id);
    }
}
