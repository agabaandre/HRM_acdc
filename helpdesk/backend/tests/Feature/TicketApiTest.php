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
            'category_id' => $cat->id,
            'description' => 'First ticket',
        ])->json('data.id');

        $secondId = (int) $this->postJson('/api/v1/tickets', [
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

    public function test_admin_can_delete_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(88802, HelpdeskProfile::ROLE_ADMIN);
        Sanctum::actingAs($user);

        $this->seedHelpdeskStaffDirectoryCache(888021, 'other.staff@example.org', 'Other', 'Staff');

        $tid = $this->postJson('/api/v1/tickets', [
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
}
