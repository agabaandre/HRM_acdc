<?php

namespace Tests\Feature;

use App\Mail\TicketStaffReplyMail;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketStaffCommentTest extends TestCase
{
    use RefreshDatabase;

    private function agent(int $sid = 501, string $email = 'agent@example.org'): User
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

    private function openTicket(User $agent, string $requesterEmail = 'requester@example.org'): HelpdeskTicket
    {
        $cat = HelpdeskCategory::query()->firstOrFail();

        return HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-STAFF-C1',
            'category_id' => $cat->id,
            'subject' => 'SAP number correction',
            'description' => 'Please update my SAP number.',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 601,
            'requester_name' => 'Halifa Mbae',
            'requester_email' => $requesterEmail,
            'assigned_user_id' => $agent->id,
        ]);
    }

    public function test_agent_public_comment_emails_requester(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);

        $agent = $this->agent();
        $ticket = $this->openTicket($agent);

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'We have updated your SAP number in the system.',
        ])->assertCreated();

        Mail::assertSent(TicketStaffReplyMail::class, 1);
        Mail::assertSent(TicketStaffReplyMail::class, function (TicketStaffReplyMail $mail) {
            return $mail->hasTo('requester@example.org')
                && str_contains($mail->comment->body, 'updated your SAP number');
        });
    }

    public function test_agent_internal_comment_does_not_email_requester(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);

        $agent = $this->agent();
        $ticket = $this->openTicket($agent);

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'Waiting on HR to confirm.',
            'is_internal' => true,
        ])->assertCreated();

        Mail::assertNotSent(TicketStaffReplyMail::class);
    }

    public function test_agent_comment_skips_email_when_requester_address_invalid(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);

        $agent = $this->agent();
        $ticket = $this->openTicket($agent, 'not-an-email');

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'Update complete.',
        ])->assertCreated();

        Mail::assertNotSent(TicketStaffReplyMail::class);
    }
}
