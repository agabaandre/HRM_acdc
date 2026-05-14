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

class TicketPriorityAndSubjectTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $staffId = 501): User
    {
        $user = User::factory()->create(['name' => 'Requester One', 'email' => 'req-'.$staffId.'@example.org']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    private function agent(int $staffId = 502): User
    {
        $user = User::factory()->create(['name' => 'Agent One', 'email' => 'ag-'.$staffId.'@example.org']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_requester_cannot_set_priority_on_create(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        Sanctum::actingAs($this->user(601));

        $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'VPN down',
            'priority' => 'critical',
        ])->assertStatus(422);
    }

    public function test_requester_gets_auto_subject_and_medium_priority(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        Sanctum::actingAs($this->user(602));

        $res = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'Cannot access email from laptop',
        ]);

        $res->assertCreated();
        $subject = (string) $res->json('data.subject');
        $this->assertStringContainsString($cat->name, $subject);
        $this->assertStringContainsString('Requester One', $subject);
        $this->assertLessThanOrEqual(199, strlen($subject));
        $this->assertSame('medium', $res->json('data.priority'));
    }

    public function test_agent_can_set_priority_on_create(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        Sanctum::actingAs($this->agent(603));

        $this->seedHelpdeskStaffDirectoryCache(999001, 'affected@example.org', 'Affected', 'User');

        $res = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'Urgent outage',
            'priority' => 'high',
            'requester_staff_id' => 999001,
        ]);

        $res->assertCreated()->assertJsonPath('data.priority', 'high');
    }

    public function test_requester_cannot_update_priority(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $user = $this->user(604);
        Sanctum::actingAs($user);

        $tid = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'x',
        ])->json('data.id');

        $this->patchJson('/api/v1/tickets/'.$tid, [
            'priority' => 'critical',
        ])->assertStatus(422);
    }

    public function test_agent_can_update_priority_on_assigned_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent(605);
        $requester = $this->user(606);

        Sanctum::actingAs($requester);
        $tid = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'Need help',
        ])->json('data.id');

        $ticket = HelpdeskTicket::query()->findOrFail($tid);
        $ticket->forceFill(['assigned_user_id' => $agent->id])->save();

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$tid, [
            'priority' => 'critical',
        ])->assertOk()->assertJsonPath('data.priority', 'critical');
    }
}
