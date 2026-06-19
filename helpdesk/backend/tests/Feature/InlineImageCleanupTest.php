<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InlineImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function agent(int $staffId = 5001): User
    {
        $user = User::factory()->create([
            'email' => "inline-agent-{$staffId}@example.org",
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_agent_can_delete_ticket_inline_editor_image(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent();

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-IMG-0001',
            'category_id' => $cat->id,
            'subject' => 'Inline image',
            'description' => '<p>Test</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 6001,
            'assigned_user_id' => $agent->id,
        ]);

        $path = 'helpdesk/'.$ticket->id.'/inline/shot.png';
        Storage::disk('public')->put($path, 'png-bytes');

        $attachment = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'shot.png',
            'size_bytes' => 9,
            'mime_type' => 'image/png',
            'uploaded_by' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/inline-images/'.$attachment->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('helpdesk_ticket_attachments', ['id' => $attachment->id]);
    }

    public function test_cannot_delete_regular_ticket_attachment_via_inline_endpoint(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->agent();

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-IMG-0002',
            'category_id' => $cat->id,
            'subject' => 'File',
            'description' => '<p>Test</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 6002,
            'assigned_user_id' => $agent->id,
        ]);

        $path = 'helpdesk/'.$ticket->id.'/report.pdf';
        Storage::disk('public')->put($path, 'pdf');

        $attachment = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'report.pdf',
            'size_bytes' => 3,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->deleteJson('/api/v1/tickets/'.$ticket->id.'/inline-images/'.$attachment->id)
            ->assertStatus(422);

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('helpdesk_ticket_attachments', ['id' => $attachment->id]);
    }

    public function test_user_can_delete_own_draft_rich_text_image(): void
    {
        Storage::fake('public');
        $user = $this->agent(5002);

        $path = 'helpdesk/rich-text/'.$user->id.'/draft.png';
        Storage::disk('public')->put($path, 'png');
        $url = Storage::disk('public')->url($path);

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/rich-text-images', ['url' => $url])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_cannot_delete_another_users_rich_text_image(): void
    {
        Storage::fake('public');
        $owner = $this->agent(5003);
        $intruder = $this->agent(5004);

        $path = 'helpdesk/rich-text/'.$owner->id.'/private.png';
        Storage::disk('public')->put($path, 'png');
        $url = Storage::disk('public')->url($path);

        Sanctum::actingAs($intruder);
        $this->deleteJson('/api/v1/rich-text-images', ['url' => $url])
            ->assertNotFound();

        Storage::disk('public')->assertExists($path);
    }

    public function test_rich_text_image_upload_and_delete_round_trip(): void
    {
        Storage::fake('public');
        $user = $this->agent(5005);

        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('paste.png', 40, 40);

        $upload = $this->postJson('/api/v1/rich-text-images', ['image' => $file])
            ->assertCreated();

        $url = $upload->json('data.url');
        $this->assertIsString($url);

        $this->deleteJson('/api/v1/rich-text-images', ['url' => $url])
            ->assertOk();
    }
}
