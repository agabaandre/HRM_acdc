<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketAttachmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900010,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    private function agent(array $overrides = []): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create(array_merge([
            'user_id' => $user->id,
            'staff_id' => 900020 + $user->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ], $overrides));

        return $user->fresh(['helpdeskProfile']);
    }

    private function ticket(User $assignee): HelpdeskTicket
    {
        $cat = HelpdeskCategory::query()->firstOrFail();

        return HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-DEL-'.str_pad((string) $assignee->id, 4, '0', STR_PAD_LEFT),
            'category_id' => $cat->id,
            'subject' => 'Attachment delete',
            'description' => '<p>Test</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 700001,
            'assigned_user_id' => $assignee->id,
        ]);
    }

    private function requestAttachment(HelpdeskTicket $ticket, User $uploader): HelpdeskTicketAttachment
    {
        $path = 'helpdesk/'.$ticket->id.'/report.pdf';
        Storage::disk('public')->put($path, 'pdf');

        return HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'report.pdf',
            'size_bytes' => 3,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function test_admin_can_delete_request_attachment_on_open_ticket(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $attachment = $this->requestAttachment($ticket, $admin);

        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/attachments/'.$attachment->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk('public')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('helpdesk_ticket_attachments', ['id' => $attachment->id]);
    }

    public function test_agent_with_permission_can_delete_request_attachment(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $agent = $this->agent(['can_delete_request_attachments' => true]);
        $ticket = $this->ticket($agent);
        $attachment = $this->requestAttachment($ticket, $agent);

        Sanctum::actingAs($agent);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/attachments/'.$attachment->id)
            ->assertOk();
    }

    public function test_agent_without_permission_cannot_delete_request_attachment(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $agent = $this->agent(['can_delete_request_attachments' => false]);
        $ticket = $this->ticket($agent);
        $attachment = $this->requestAttachment($ticket, $agent);

        Sanctum::actingAs($agent);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/attachments/'.$attachment->id)
            ->assertForbidden();
    }

    public function test_cannot_delete_request_attachment_on_closed_ticket(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $ticket->status = 'closed';
        $ticket->save();
        $attachment = $this->requestAttachment($ticket, $admin);

        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/attachments/'.$attachment->id)
            ->assertForbidden();
    }

    public function test_agent_with_permission_can_change_category_on_open_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $categories = HelpdeskCategory::query()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $categories->count());

        $agent = $this->agent(['can_change_ticket_category' => true]);
        $ticket = $this->ticket($agent);
        $nextCategoryId = (int) $categories->last()->id;

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'category_id' => $nextCategoryId,
        ])
            ->assertOk()
            ->assertJsonPath('data.category.id', $nextCategoryId);
    }

    public function test_agent_without_permission_cannot_change_category(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $categories = HelpdeskCategory::query()->orderBy('id')->get();
        $agent = $this->agent(['can_change_ticket_category' => false]);
        $ticket = $this->ticket($agent);
        $nextCategoryId = (int) $categories->last()->id;

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'category_id' => $nextCategoryId,
        ])->assertForbidden();
    }
}
