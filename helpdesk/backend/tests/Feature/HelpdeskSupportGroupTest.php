<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use App\Services\AgentCategoryRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskSupportGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_inherits_categories_from_support_group(): void
    {
        $category = HelpdeskCategory::query()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $agent = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $agent->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_id' => 9001,
        ]);

        $group = HelpdeskSupportGroup::query()->create([
            'name' => 'Software Development',
            'slug' => 'software-development',
            'sort_order' => 10,
            'is_active' => true,
            'is_system' => true,
        ]);
        $group->categories()->sync([$category->id]);
        $group->members()->sync([$agent->id]);

        $routing = app(AgentCategoryRoutingService::class);

        $this->assertTrue($routing->agentHandlesCategory($agent->id, $category->id));
        $this->assertSame(
            [$category->id],
            array_column($routing->groupInheritedCategoriesForUser($agent->id), 'id')
        );
    }

    public function test_admin_can_list_support_groups(): void
    {
        $admin = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 1,
            'grant_helpdesk_admin' => true,
        ]);

        HelpdeskSupportGroup::query()->create([
            'name' => 'Systems Administration',
            'slug' => 'systems-administration',
            'sort_order' => 40,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/support-groups');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Systems Administration');
    }
}
