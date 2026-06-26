<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicScreenApiTest extends TestCase
{
    use RefreshDatabase;

    private function helpdeskUser(int $staffId, string $role = HelpdeskProfile::ROLE_USER): User
    {
        $user = User::factory()->create([
            'email' => 'screen-user-'.$staffId.'@example.org',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_screen_includes_avg_first_response_from_staff_comments(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $requester = $this->helpdeskUser(99001);
        $agent = $this->helpdeskUser(99002, HelpdeskProfile::ROLE_AGENT);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009900',
            'category_id' => $cat->id,
            'subject' => 'VPN issue',
            'description' => 'Cannot connect',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 99001,
            'requester_name' => $requester->name,
            'requester_email' => $requester->email,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'author_staff_id' => 99002,
            'is_internal' => false,
            'body' => 'We are looking into this.',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.wait.avg_first_response_minutes', 20);
        $response->assertJsonPath('data.wait.window_label', 'last 24h');
    }

    public function test_agent_public_comment_sets_first_response_at(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $requester = $this->helpdeskUser(99101);
        $agent = $this->helpdeskUser(99102, HelpdeskProfile::ROLE_AGENT);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009901',
            'category_id' => $cat->id,
            'subject' => 'Email',
            'description' => 'Inbox full',
            'priority' => 'low',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 99101,
            'requester_name' => $requester->name,
            'requester_email' => $requester->email,
            'assigned_user_id' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/tickets/'.$ticket->id.'/comments', [
            'body' => 'Please clear deleted items.',
        ])->assertCreated();

        $ticket->refresh();
        $this->assertNotNull($ticket->first_response_at);
    }
}
