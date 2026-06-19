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
        $res->assertOk();

        $agentIds = collect($res->json('data.agents'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($agentB->id, $agentIds);
        $this->assertNotContains($agentA->id, $agentIds);
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
        $this->assertDatabaseHas('helpdesk_ticket_comments', [
            'ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }
}
