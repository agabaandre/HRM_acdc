<?php

namespace Tests\Feature;

use App\Mail\TicketAssignedToAgentMail;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function requester(int $sid = 401): User
    {
        $user = User::factory()->create(['email' => 'req'.$sid.'@example.org', 'name' => 'Req '.$sid]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $sid,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    private function agent(int $sid, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => 'Agent '.$sid]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $sid,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    private function supervisor(int $sid, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => 'Sup '.$sid]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $sid,
            'role' => HelpdeskProfile::ROLE_SUPERVISOR,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_end_user_ticket_auto_assign_sends_one_assignment_email(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $this->agent(501, 'pickme@example.org');

        Sanctum::actingAs($this->requester(601));
        $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'Need VPN',
        ])->assertCreated();

        Mail::assertSent(TicketAssignedToAgentMail::class, 1);
        Mail::assertSent(TicketAssignedToAgentMail::class, function (TicketAssignedToAgentMail $mail) {
            return $mail->hasTo('pickme@example.org')
                && $mail->isReassignment === false;
        });
    }

    public function test_supervisor_reassign_sends_reassignment_email_to_new_agent(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agent1 = $this->agent(801, 'first-agent@example.org');
        $this->agent(802, 'second-agent@example.org');
        $sup = $this->supervisor(803, 'sup@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-R1',
            'category_id' => $cat->id,
            'subject' => 'Handoff',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'R',
            'requester_email' => 'r@example.org',
            'assigned_user_id' => $agent1->id,
        ]);

        Mail::assertSent(TicketAssignedToAgentMail::class, 1);

        Sanctum::actingAs($sup);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'assigned_user_id' => User::query()->where('email', 'second-agent@example.org')->value('id'),
        ])->assertOk();

        Mail::assertSent(TicketAssignedToAgentMail::class, 2);
        Mail::assertSent(TicketAssignedToAgentMail::class, function (TicketAssignedToAgentMail $mail) {
            return $mail->hasTo('second-agent@example.org')
                && $mail->isReassignment === true;
        });
    }
}
