<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketReassignEligibleAgentsTest extends TestCase
{
    use RefreshDatabase;

    private function agent(int $staffId, string $email, array $categoryIds = []): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => 'Agent '.$staffId]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);
        if ($categoryIds !== []) {
            $user->helpdeskAgentCategories()->sync($categoryIds);
        }

        return $user->fresh(['helpdeskProfile']);
    }

    private function reassigner(int $staffId): User
    {
        $user = User::factory()->create(['email' => 'reassigner'.$staffId.'@example.org']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => HelpdeskProfile::ROLE_SUPERVISOR,
            'can_reassign_tickets' => true,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_eligible_agents_lists_all_assignable_agents_not_only_category_routed(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $categories = HelpdeskCategory::query()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $categories->count());

        $catA = $categories[0];
        $catB = $categories[1];

        $agentA = $this->agent(9101, 'agent-a@example.org', [$catA->id]);
        $agentB = $this->agent(9102, 'agent-b@example.org', [$catB->id]);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-ELIG-1',
            'category_id' => $catA->id,
            'subject' => 'Category A ticket',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        Sanctum::actingAs($this->reassigner(9100));
        $res = $this->getJson('/api/v1/tickets/'.$ticket->id.'/eligible-agents');
        $res->assertOk()
            ->assertJsonPath('data.current.assignee_user_ids.0', $agentA->id)
            ->assertJsonPath('data.current.priority', 'medium')
            ->assertJsonPath('data.current.category_id', $catA->id);

        $agentIds = collect($res->json('data.agents'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($agentB->id, $agentIds);
        $this->assertContains($agentA->id, $agentIds);
    }

    public function test_reassign_endpoint_updates_assignee_and_records_internal_comment(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agentA = $this->agent(9201, 'reassign-from@example.org');
        $agentB = $this->agent(9202, 'reassign-to@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-REAS-1',
            'category_id' => $cat->id,
            'subject' => 'Handoff ticket',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        Sanctum::actingAs($this->reassigner(9200));
        $res = $this->postJson('/api/v1/tickets/'.$ticket->id.'/reassign', [
            'assignee_user_id' => $agentB->id,
            'reason' => 'Covering while colleague is away',
        ]);

        $res->assertOk();
        $ticket->refresh();
        $this->assertSame($agentB->id, (int) $ticket->assigned_user_id);
        $this->assertDatabaseHas('helpdesk_ticket_assignees', [
            'helpdesk_ticket_id' => $ticket->id,
            'user_id' => $agentB->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('helpdesk_ticket_comments', [
            'ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }

    public function test_reassign_supports_multiple_agents_priority_and_category(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $categories = HelpdeskCategory::query()->orderBy('id')->get();
        $catA = $categories[0];
        $catB = $categories[1];

        $agentA = $this->agent(9301, 'multi-a@example.org');
        $agentB = $this->agent(9302, 'multi-b@example.org');
        $agentC = $this->agent(9303, 'multi-c@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-MULTI-1',
            'category_id' => $catA->id,
            'subject' => 'Multi assignee ticket',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        Sanctum::actingAs($this->reassigner(9300));
        $res = $this->postJson('/api/v1/tickets/'.$ticket->id.'/reassign', [
            'assignee_user_ids' => [$agentB->id, $agentC->id],
            'priority' => 'critical',
            'category_id' => $catB->id,
            'reason' => 'Adding coverage and raising priority',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.category.id', $catB->id);

        $ticket->refresh();
        $this->assertSame($agentB->id, (int) $ticket->assigned_user_id);
        $this->assertSame('critical', $ticket->priority);
        $this->assertSame($catB->id, (int) $ticket->category_id);
        $this->assertDatabaseHas('helpdesk_ticket_assignees', [
            'helpdesk_ticket_id' => $ticket->id,
            'user_id' => $agentB->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('helpdesk_ticket_assignees', [
            'helpdesk_ticket_id' => $ticket->id,
            'user_id' => $agentC->id,
            'is_primary' => false,
        ]);
    }
}
