<?php

namespace Tests\Feature;

use App\Mail\TicketRequesterCommentMail;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketRequesterCommentTest extends TestCase
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

    private function closedTicket(User $agent, int $requesterStaffId): HelpdeskTicket
    {
        $cat = HelpdeskCategory::query()->firstOrFail();

        return HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-C1',
            'category_id' => $cat->id,
            'subject' => 'VPN issue',
            'description' => 'Cannot connect',
            'priority' => 'medium',
            'status' => 'closed',
            'source' => 'web',
            'requester_staff_id' => $requesterStaffId,
            'requester_name' => 'Req',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agent->id,
            'closed_at' => now(),
        ]);
    }

    public function test_requester_comment_emails_assignee_and_can_reopen_when_enabled(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);

        $agent = $this->agent(501, 'agent@example.org');
        $requester = $this->requester(601);
        $ticket = $this->closedTicket($agent, 601);

        Sanctum::actingAs($requester);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'Still broken after the fix.',
            'reopen_ticket' => true,
        ])->assertCreated()
            ->assertJsonPath('meta.ticket_reopened', true);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->closed_at);

        Mail::assertSent(TicketRequesterCommentMail::class, 1);
        Mail::assertSent(TicketRequesterCommentMail::class, function (TicketRequesterCommentMail $mail) {
            return $mail->hasTo('agent@example.org')
                && $mail->ticketReopened === true
                && str_contains($mail->comment->body, 'Still broken');
        });
    }

    public function test_requester_comment_does_not_email_or_reopen_when_setting_disabled(): void
    {
        Mail::fake();
        $this->seed(HelpdeskCategorySeeder::class);
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP, '0');

        $agent = $this->agent(701, 'agent2@example.org');
        $requester = $this->requester(801);
        $ticket = $this->closedTicket($agent, 801);

        Sanctum::actingAs($requester);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'Please help again.',
            'reopen_ticket' => true,
        ])->assertCreated()
            ->assertJsonPath('meta.ticket_reopened', false);

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);

        Mail::assertNothingSent();
    }
}
