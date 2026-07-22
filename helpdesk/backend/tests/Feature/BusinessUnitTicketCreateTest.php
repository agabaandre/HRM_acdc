<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTicketWithAi;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\TicketAiCategorizationService;
use App\Services\TicketAssignmentService;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessUnitTicketCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingHelpdeskUser(int $staffId = 9001, string $role = HelpdeskProfile::ROLE_USER): User
    {
        $user = User::factory()->create([
            'email' => 'bu-user-'.$staffId.'@example.org',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    private function assignAgentToUnit(HelpdeskBusinessUnit $unit, int $staffId): User
    {
        $categoryIds = HelpdeskCategory::query()
            ->where('business_unit_id', $unit->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $agent = $this->actingHelpdeskUser($staffId, HelpdeskProfile::ROLE_AGENT);
        $agent->helpdeskAgentCategories()->sync($categoryIds);

        return $agent;
    }

    public function test_business_units_index_includes_meta_and_categories(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $category = HelpdeskCategory::query()->where('business_unit_id', $unit->id)->firstOrFail();

        $agent = $this->actingHelpdeskUser(70100, HelpdeskProfile::ROLE_AGENT);
        $agent->helpdeskAgentCategories()->sync([$category->id]);

        $user = $this->actingHelpdeskUser(70101);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/business-units');
        $res->assertOk();
        $this->assertArrayHasKey('show_issue_category_on_request_form', $res->json('meta'));
        $this->assertTrue((bool) $res->json('meta.agent_coverage_enforced'));
        $this->assertNotEmpty($res->json('data'));
        $this->assertNotEmpty($res->json('data.0.categories'));
    }

    public function test_business_units_without_agents_are_hidden_from_request_form(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $it = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $protocol = HelpdeskBusinessUnit::query()->where('slug', 'protocol')->first();
        if ($protocol === null) {
            $protocol = HelpdeskBusinessUnit::query()->create([
                'name' => 'Protocol',
                'slug' => 'protocol',
                'sort_order' => 40,
                'is_active' => true,
                'allows_anonymous' => false,
            ]);
            HelpdeskCategory::query()->create([
                'business_unit_id' => $protocol->id,
                'name' => 'Visa Processing',
                'slug' => 'visa-processing-test',
                'sort_order' => 1,
                'is_active' => true,
                'default_priority' => 'medium',
            ]);
        }

        $itCategory = HelpdeskCategory::query()->where('business_unit_id', $it->id)->firstOrFail();
        $agent = $this->actingHelpdeskUser(70111, HelpdeskProfile::ROLE_AGENT);
        $agent->helpdeskAgentCategories()->sync([$itCategory->id]);

        $user = $this->actingHelpdeskUser(70112);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/business-units');
        $res->assertOk();
        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertContains('it-mis', $slugs);
        $this->assertNotContains('protocol', $slugs);
    }

    public function test_cannot_create_ticket_for_business_unit_without_agents(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();

        $user = $this->actingHelpdeskUser(70113);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $unit->id,
            'description' => '<p>No agents configured for this unit</p>',
        ])->assertStatus(422);
    }

    public function test_end_user_can_create_ticket_with_business_unit_only_and_dispatches_ai_job(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $category = HelpdeskCategory::query()->where('business_unit_id', $unit->id)->firstOrFail();
        $agent = $this->actingHelpdeskUser(70114, HelpdeskProfile::ROLE_AGENT);
        $agent->helpdeskAgentCategories()->sync([$category->id]);

        $user = $this->actingHelpdeskUser(70102);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $unit->id,
            'description' => '<p>Outlook will not open after restart</p>',
        ]);

        $res->assertCreated();
        $this->assertSame($unit->id, (int) $res->json('data.business_unit_id'));
        $this->assertNull($res->json('data.category_id'));
        Bus::assertDispatched(CategorizeTicketWithAi::class);
    }

    public function test_category_required_when_setting_enabled(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM],
            ['value' => '1']
        );
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $this->assignAgentToUnit($unit, 70115);

        $user = $this->actingHelpdeskUser(70103);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $unit->id,
            'description' => '<p>Need VPN access</p>',
        ])->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    public function test_anonymous_reporting_only_for_internal_oversight(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $it = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $io = HelpdeskBusinessUnit::query()->where('slug', 'internal-oversight')->firstOrFail();
        $this->assignAgentToUnit($it, 70116);
        $this->assignAgentToUnit($io, 70117);

        $user = $this->actingHelpdeskUser(70104);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $it->id,
            'description' => '<p>Should fail anonymous</p>',
            'is_anonymous' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['is_anonymous']);

        $ok = $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $io->id,
            'description' => '<p>Confidential concern</p>',
            'is_anonymous' => true,
        ]);
        $ok->assertCreated();
        $this->assertTrue((bool) $ok->json('data.is_anonymous'));
        $this->assertSame('Anonymous', $ok->json('data.requester_name'));
        $this->assertNull($ok->json('data.requester_email'));
        $this->assertNull($ok->json('data.requester_staff_id'));
    }

    public function test_admin_can_crud_business_units(): void
    {
        $admin = $this->actingHelpdeskUser(70105, HelpdeskProfile::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/v1/admin/business-units', [
            'name' => 'Legal Affairs',
            'sort_order' => 60,
        ]);
        $create->assertCreated();
        $id = (int) $create->json('data.id');

        $this->putJson('/api/v1/admin/business-units/'.$id, [
            'name' => 'Legal',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Legal');

        $this->deleteJson('/api/v1/admin/business-units/'.$id)->assertOk();
        $this->assertDatabaseMissing('helpdesk_business_units', ['id' => $id]);
    }

    public function test_ai_job_falls_back_to_admin_round_robin_when_categorization_fails(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();

        // Multiple categories so single-category auto-pick does not succeed.
        HelpdeskCategory::query()->where('business_unit_id', $unit->id)->update([
            'ai_description' => 'zzz unmatched tokens only',
        ]);

        $admin = $this->actingHelpdeskUser(70106, HelpdeskProfile::ROLE_ADMIN);
        $creator = $this->actingHelpdeskUser(70107);

        $ticket = HelpdeskTicket::query()->create([
            'created_by_user_id' => $creator->id,
            'ticket_number' => 'HD-AI-1',
            'category_id' => null,
            'business_unit_id' => $unit->id,
            'subject' => 'BU only',
            'description' => '<p>completely unrelated xyzzy content</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 70107,
            'requester_name' => 'Creator',
            'requester_email' => 'c@example.org',
        ]);

        $this->mock(TicketAiCategorizationService::class, function ($mock) {
            $mock->shouldReceive('categorize')->once()->andReturn(null);
        });

        (new CategorizeTicketWithAi($ticket->id))->handle(
            app(TicketAiCategorizationService::class),
            app(TicketAssignmentService::class),
            app(\App\Services\TicketAssigneeService::class),
            app(\App\Services\TicketSubjectGenerator::class),
            app(\App\Services\TicketPriorityResolver::class),
        );

        $ticket->refresh();
        $this->assertSame($admin->id, (int) $ticket->assigned_user_id);
        $this->assertTrue((bool) ($ticket->meta['ai_categorization_failed'] ?? false));
    }
}
