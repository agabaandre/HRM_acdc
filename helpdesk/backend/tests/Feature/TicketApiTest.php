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

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingHelpdeskUser(int $staffId = 9001, string $role = HelpdeskProfile::ROLE_USER): User
    {
        $user = User::factory()->create([
            'email' => 'ticket-user-'.$staffId.'@example.org',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_authenticated_user_can_create_and_list_own_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(77701);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => '<p>Outlook error</p>',
        ]);

        $create->assertCreated();
        $subject = (string) $create->json('data.subject');
        $this->assertStringContainsString($cat->name, $subject);
        $this->assertLessThanOrEqual(199, strlen($subject));

        $list = $this->getJson('/api/v1/tickets');
        $list->assertOk();
        $this->assertNotEmpty($list->json('data'));
    }

    public function test_duplicate_create_with_same_idempotency_key_returns_existing_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(77703);
        Sanctum::actingAs($user);

        $payload = [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => '<p>Network issue</p>',
        ];
        $headers = ['Idempotency-Key' => 'create-'.uniqid('', true)];

        $first = $this->postJson('/api/v1/tickets', $payload, $headers);
        $first->assertCreated();
        $ticketId = (int) $first->json('data.id');

        $second = $this->postJson('/api/v1/tickets', $payload, $headers);
        $second->assertOk();
        $this->assertSame($ticketId, (int) $second->json('data.id'));
        $this->assertDatabaseCount('helpdesk_tickets', 1);
    }

    public function test_end_user_can_create_ticket_on_behalf_of_other_staff_when_directory_cached(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $this->seedHelpdeskStaffDirectoryCache(77702, 'colleague@example.org', 'Col', 'League', 1, 2, 'Addis Hub');

        $user = $this->actingHelpdeskUser(77701);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => 'On behalf test',
            'requester_staff_id' => 77702,
        ]);

        $res->assertCreated();
        $this->assertSame(77702, (int) $res->json('data.requester_staff_id'));
        $this->assertSame('colleague@example.org', $res->json('data.requester_email'));
    }

    public function test_ticket_list_supports_sort_direction(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(77711);
        Sanctum::actingAs($user);

        $firstId = (int) $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => 'First ticket',
        ])->json('data.id');

        $secondId = (int) $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => 'Second ticket',
        ])->json('data.id');

        $desc = $this->getJson('/api/v1/tickets?sort_by=id&sort_dir=desc');
        $desc->assertOk();
        $descIds = array_map('intval', array_column($desc->json('data'), 'id'));
        $this->assertSame([$secondId, $firstId], array_slice($descIds, 0, 2));

        $asc = $this->getJson('/api/v1/tickets?sort_by=id&sort_dir=asc');
        $asc->assertOk();
        $ascIds = array_map('intval', array_column($asc->json('data'), 'id'));
        $this->assertSame([$firstId, $secondId], array_slice($ascIds, 0, 2));
    }

    public function test_ticket_list_puts_latest_unassigned_ahead_of_assigned(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $admin = $this->actingHelpdeskUser(88101, HelpdeskProfile::ROLE_ADMIN);
        $agent = $this->actingHelpdeskUser(88102, HelpdeskProfile::ROLE_AGENT);
        Sanctum::actingAs($admin);

        $unassignedOlder = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SORT-U1',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Unassigned older',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'email',
            'assigned_user_id' => null,
            'requester_name' => 'Ada',
            'requester_email' => 'ada@example.org',
        ]);
        $assignedOlder = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SORT-A',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned older',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'email',
            'assigned_user_id' => $agent->id,
            'requester_name' => 'Bea',
            'requester_email' => 'bea@example.org',
        ]);
        $unassignedLatest = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SORT-U2',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Unassigned latest',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'email',
            'assigned_user_id' => null,
            'requester_name' => 'Cara',
            'requester_email' => 'cara@example.org',
        ]);
        $assignedNewest = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SORT-B',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned newest',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'portal',
            'assigned_user_id' => $agent->id,
            'requester_name' => 'Dee',
            'requester_email' => 'dee@example.org',
        ]);

        $res = $this->getJson('/api/v1/tickets');
        $res->assertOk();
        $ids = array_map('intval', array_column($res->json('data'), 'id'));
        $this->assertSame(
            [$unassignedLatest->id, $unassignedOlder->id, $assignedNewest->id, $assignedOlder->id],
            array_values(array_intersect($ids, [
                $unassignedLatest->id,
                $unassignedOlder->id,
                $assignedNewest->id,
                $assignedOlder->id,
            ])),
        );
        $this->assertSame($unassignedLatest->id, $ids[0]);
    }

    public function test_admin_can_delete_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(88802, HelpdeskProfile::ROLE_ADMIN);
        Sanctum::actingAs($user);

        $this->seedHelpdeskStaffDirectoryCache(888021, 'other.staff@example.org', 'Other', 'Staff');

        $tid = $this->postJson('/api/v1/tickets', [
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'description' => 'x',
            'requester_staff_id' => 888021,
        ])->json('data.id');

        $this->deleteJson('/api/v1/tickets/'.$tid)->assertNoContent();
        $this->assertDatabaseMissing('helpdesk_tickets', ['id' => $tid]);
    }

    public function test_ticket_list_assigned_to_me_returns_only_agent_assignments(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agentA = $this->actingHelpdeskUser(88001, HelpdeskProfile::ROLE_AGENT);
        $agentB = $this->actingHelpdeskUser(88002, HelpdeskProfile::ROLE_AGENT);

        $mine = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-MINE-1',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned to agent A',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-OTHER-1',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned to agent B',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 901,
            'requester_name' => 'Other',
            'requester_email' => 'other@example.org',
            'assigned_user_id' => $agentB->id,
        ]);

        Sanctum::actingAs($agentA);
        $res = $this->getJson('/api/v1/tickets?assigned_to_me=1&status_in=open');
        $res->assertOk();

        $ids = array_map('intval', array_column($res->json('data'), 'id'));
        $this->assertContains($mine->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_ticket_list_filters_by_assigned_agent(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agentA = $this->actingHelpdeskUser(88011, HelpdeskProfile::ROLE_AGENT);
        $agentB = $this->actingHelpdeskUser(88012, HelpdeskProfile::ROLE_AGENT);

        $mine = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-AGENT-A',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned to A',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 910,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-AGENT-B',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Assigned to B',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 911,
            'requester_name' => 'Other',
            'requester_email' => 'other@example.org',
            'assigned_user_id' => $agentB->id,
        ]);

        Sanctum::actingAs($agentA);
        $res = $this->getJson('/api/v1/tickets?assigned_user_id='.$agentA->id);
        $res->assertOk();

        $ids = array_map('intval', array_column($res->json('data'), 'id'));
        $this->assertSame([$mine->id], $ids);
    }

    public function test_ticket_list_date_preset_defaults_to_all(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->actingHelpdeskUser(88021, HelpdeskProfile::ROLE_AGENT);

        $recent = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-DATE-NEW',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Recent',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 920,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agent->id,
        ]);

        $old = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-DATE-OLD',
            'business_unit_id' => $cat->business_unit_id,
            'category_id' => $cat->id,
            'subject' => 'Old',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 921,
            'requester_name' => 'Other',
            'requester_email' => 'other@example.org',
            'assigned_user_id' => $agent->id,
        ]);
        $old->created_at = now()->subDays(10);
        $old->save();

        Sanctum::actingAs($agent);
        $all = $this->getJson('/api/v1/tickets');
        $all->assertOk();
        $allIds = array_map('intval', array_column($all->json('data'), 'id'));
        $this->assertContains($recent->id, $allIds);
        $this->assertContains($old->id, $allIds);

        $today = $this->getJson('/api/v1/tickets?date_preset=today');
        $today->assertOk();
        $todayIds = array_map('intval', array_column($today->json('data'), 'id'));
        $this->assertContains($recent->id, $todayIds);
        $this->assertNotContains($old->id, $todayIds);
    }

    public function test_ticket_filter_agents_lists_assignable_agents(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $agentA = $this->actingHelpdeskUser(88031, HelpdeskProfile::ROLE_AGENT);
        $agentB = $this->actingHelpdeskUser(88032, HelpdeskProfile::ROLE_AGENT);
        $this->actingHelpdeskUser(88033, HelpdeskProfile::ROLE_USER);

        Sanctum::actingAs($agentA);
        $res = $this->getJson('/api/v1/tickets/filter-agents');
        $res->assertOk();

        $ids = array_map('intval', array_column($res->json('data'), 'id'));
        $this->assertContains($agentA->id, $ids);
        $this->assertContains($agentB->id, $ids);
        $this->assertCount(2, $ids);
    }
}
