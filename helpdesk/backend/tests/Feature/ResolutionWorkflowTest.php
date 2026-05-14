<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResolutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_confirm_resolution_completes_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $token = Str::random(48);
        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-TEST-0001',
            'category_id' => $cat->id,
            'subject' => 'Test',
            'description' => 'x',
            'priority' => 'medium',
            'status' => 'awaiting_requester_confirmation',
            'source' => 'web',
            'requester_staff_id' => 1,
            'requester_name' => 'U',
            'requester_email' => 'u@example.org',
            'resolution_summary' => 'Fixed VPN',
            'resolution_confirm_token' => $token,
            'resolution_submitted_by_user_id' => null,
        ]);

        $this->postJson('/api/v1/public/tickets/confirm-resolution', [
            'token' => $token,
        ])->assertOk();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNull($ticket->resolution_confirm_token);
    }
}
