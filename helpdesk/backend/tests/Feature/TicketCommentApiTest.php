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

class TicketCommentApiTest extends TestCase
{
    use RefreshDatabase;

    private function helpdeskUser(int $staffId, string $role = HelpdeskProfile::ROLE_USER): User
    {
        $user = User::factory()->create([
            'email' => 'comment-user-'.$staffId.'@example.org',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_requester_can_post_and_list_public_comments(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->helpdeskUser(66001);
        Sanctum::actingAs($user);

        $tid = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'Slow',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/tickets/'.$tid.'/comments', [
            'body' => 'Happens after 5pm',
        ])->assertCreated()->assertJsonPath('data.body', 'Happens after 5pm');

        $list = $this->getJson('/api/v1/tickets/'.$tid.'/comments');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    public function test_end_user_does_not_see_internal_comments(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $requester = $this->helpdeskUser(77001);
        $admin = $this->helpdeskUser(77002, HelpdeskProfile::ROLE_ADMIN);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-000001',
            'category_id' => $cat->id,
            'subject' => 'Shared inbox',
            'description' => 'x',
            'priority' => 'low',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 77001,
            'requester_name' => $requester->name,
            'requester_email' => $requester->email,
        ]);

        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'author_staff_id' => 77002,
            'is_internal' => true,
            'body' => 'Escalated to vendor — internal',
        ]);

        Sanctum::actingAs($requester);
        $list = $this->getJson('/api/v1/tickets/'.$ticket->id.'/comments');
        $list->assertOk();
        $this->assertCount(0, $list->json('data'));
    }

    public function test_admin_lists_internal_comments(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $requester = $this->helpdeskUser(88001);
        $admin = $this->helpdeskUser(88002, HelpdeskProfile::ROLE_ADMIN);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-000002',
            'category_id' => $cat->id,
            'subject' => 'Printer',
            'description' => 'x',
            'priority' => 'low',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 88001,
            'requester_name' => $requester->name,
            'requester_email' => $requester->email,
        ]);

        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'author_staff_id' => 88002,
            'is_internal' => true,
            'body' => 'Internal note',
        ]);

        Sanctum::actingAs($admin);
        $list = $this->getJson('/api/v1/tickets/'.$ticket->id.'/comments');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }
}
