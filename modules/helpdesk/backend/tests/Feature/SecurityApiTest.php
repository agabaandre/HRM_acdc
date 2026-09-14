<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Models\User;
use App\Support\HelpdeskAttachmentUrl;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(int $staffId, string $role): User
    {
        $user = User::factory()->create([
            'email' => "sec-{$staffId}-{$role}@example.org",
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_requester_cannot_view_another_users_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $owner = $this->userWithRole(1001, HelpdeskProfile::ROLE_USER);
        $intruder = $this->userWithRole(1002, HelpdeskProfile::ROLE_USER);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SEC-0001',
            'category_id' => $cat->id,
            'subject' => 'Private',
            'description' => 'Secret issue',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 1001,
            'requester_name' => 'Owner',
            'requester_email' => 'owner@example.org',
        ]);

        Sanctum::actingAs($intruder);
        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertForbidden();
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'description' => 'Hijacked',
        ])->assertForbidden();
    }

    public function test_ticket_update_strips_script_tags_from_description(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = $this->userWithRole(2001, HelpdeskProfile::ROLE_AGENT);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SEC-0002',
            'category_id' => $cat->id,
            'subject' => 'XSS test',
            'description' => '<p>Safe</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 3001,
            'assigned_user_id' => $agent->id,
        ]);

        Sanctum::actingAs($agent);
        $this->patchJson('/api/v1/tickets/'.$ticket->id, [
            'description' => '<p>Hi</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ])->assertOk();

        $ticket->refresh();
        $this->assertStringNotContainsString('<script', (string) $ticket->description);
        $this->assertStringNotContainsString('onerror', (string) $ticket->description);
        $this->assertStringContainsString('Hi', (string) $ticket->description);
    }

    public function test_ticket_search_sql_injection_does_not_error(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $user = $this->userWithRole(4001, HelpdeskProfile::ROLE_USER);
        Sanctum::actingAs($user);

        $payload = "' OR 1=1; DROP TABLE helpdesk_tickets; --";
        $this->getJson('/api/v1/tickets?q='.urlencode($payload))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_unsigned_attachment_download_is_forbidden(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SEC-0003',
            'category_id' => $cat->id,
            'subject' => 'Files',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 5001,
        ]);

        $path = 'helpdesk/'.$ticket->id.'/secret.pdf';
        Storage::disk('public')->put($path, '%PDF-secret');
        $attachment = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'secret.pdf',
            'size_bytes' => 10,
            'mime_type' => 'application/pdf',
            'uploaded_by' => null,
        ]);

        $this->get('/api/v1/attachments/'.$attachment->id.'/file')->assertForbidden();

        Storage::disk('public')->delete($path);
    }

    public function test_signed_attachment_download_succeeds(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SEC-0004',
            'category_id' => $cat->id,
            'subject' => 'Files',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 5002,
        ]);

        $path = 'helpdesk/'.$ticket->id.'/ok.pdf';
        Storage::disk('public')->put($path, '%PDF-ok');
        $attachment = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'ok.pdf',
            'size_bytes' => 6,
            'mime_type' => 'application/pdf',
            'uploaded_by' => null,
        ]);

        $exp = time() + 3600;
        $sig = HelpdeskAttachmentUrl::sign($attachment, $exp);

        $this->get('/api/v1/attachments/'.$attachment->id.'/file?exp='.$exp.'&sig='.$sig)
            ->assertOk();

        Storage::disk('public')->delete($path);
    }

    public function test_ticket_resource_uses_signed_attachment_urls(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $user = $this->userWithRole(6001, HelpdeskProfile::ROLE_USER);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-SEC-0005',
            'category_id' => $cat->id,
            'subject' => 'URL test',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 6001,
        ]);

        $path = 'helpdesk/'.$ticket->id.'/a.png';
        Storage::disk('public')->put($path, 'png');
        HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'a.png',
            'size_bytes' => 3,
            'mime_type' => 'image/png',
            'uploaded_by' => $user->id,
        ]);

        Sanctum::actingAs($user);
        $url = (string) $this->getJson('/api/v1/tickets/'.$ticket->id)->json('data.attachments.0.url');
        $this->assertStringContainsString('/api/v1/attachments/', $url);
        $this->assertStringContainsString('sig=', $url);
        $this->assertStringNotContainsString('/storage/helpdesk/', $url);

        Storage::disk('public')->delete($path);
    }

    public function test_unauthenticated_api_returns_401(): void
    {
        $this->getJson('/api/v1/tickets')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
