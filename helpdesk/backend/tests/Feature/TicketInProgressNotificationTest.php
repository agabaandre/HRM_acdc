<?php

namespace Tests\Feature;

use App\Mail\TicketInProgressMail;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketInProgressNotificationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_moving_ticket_to_in_progress_emails_requester(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent(701, 'agent701@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-IP1',
            'category_id' => $cat->id,
            'subject' => 'VPN access',
            'description' => 'Cannot connect',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 900,
            'requester_name' => 'Jane Requester',
            'requester_email' => 'jane.requester@example.org',
            'assigned_user_id' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'status' => 'in_progress',
        ])->assertOk();

        Mail::assertSent(TicketInProgressMail::class, 1);
        Mail::assertSent(TicketInProgressMail::class, function (TicketInProgressMail $mail) use ($agent) {
            return $mail->hasTo('jane.requester@example.org')
                && $mail->ticket->ticket_number === 'HD-TEST-IP1'
                && $mail->agent?->id === $agent->id;
        });
    }

    public function test_status_change_other_than_in_progress_does_not_email_requester(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent(702, 'agent702@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-IP2',
            'category_id' => $cat->id,
            'subject' => 'Printer',
            'description' => 'Jam',
            'priority' => 'low',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 901,
            'requester_name' => 'Bob',
            'requester_email' => 'bob@example.org',
            'assigned_user_id' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'status' => 'pending',
        ])->assertOk();

        Mail::assertNotSent(TicketInProgressMail::class);
    }

    public function test_already_in_progress_status_update_does_not_re_email(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent(703, 'agent703@example.org');

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-IP3',
            'category_id' => $cat->id,
            'subject' => 'Email',
            'description' => 'Sync issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'source' => 'web',
            'requester_staff_id' => 902,
            'requester_name' => 'Sam',
            'requester_email' => 'sam@example.org',
            'assigned_user_id' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'priority' => 'critical',
        ])->assertOk();

        Mail::assertNotSent(TicketInProgressMail::class);
    }
}
