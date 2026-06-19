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

    public function test_portal_admin_designated_agent_inherits_group_routing(): void
    {
        $category = HelpdeskCategory::query()->create([
            'name' => 'Admin Routed',
            'slug' => 'admin-routed',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $adminAgent = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $adminAgent->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 9002,
            'staff_portal_role' => 10,
            'is_designated_agent' => true,
        ]);

        $group = HelpdeskSupportGroup::query()->create([
            'name' => 'Admin Group',
            'slug' => 'admin-group',
            'sort_order' => 20,
            'is_active' => true,
            'is_system' => false,
        ]);
        $group->categories()->sync([$category->id]);
        $group->members()->sync([$adminAgent->id]);

        $routing = app(AgentCategoryRoutingService::class);

        $this->assertTrue($adminAgent->fresh('helpdeskProfile')->helpdeskProfile->actsAsAgent());
        $this->assertTrue($routing->agentHandlesCategory($adminAgent->id, $category->id));
        $this->assertContains(
            $adminAgent->id,
            $routing->eligibleMemberUserIdsForGroup($group->fresh(), $category->id)
        );
    }

    public function test_designated_portal_admin_listed_in_admin_agents_api(): void
    {
        $admin = User::factory()->create(['name' => 'Portal Admin']);
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 1,
            'staff_portal_role' => 10,
            'grant_helpdesk_admin' => true,
        ]);

        $designated = User::factory()->create(['name' => 'Admin Agent']);
        HelpdeskProfile::query()->create([
            'user_id' => $designated->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 2,
            'staff_portal_role' => 10,
            'is_designated_agent' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/agents');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Admin Agent', $names);
    }
}
