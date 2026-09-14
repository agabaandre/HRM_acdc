<?php

namespace Tests\Unit;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\RichTextDataUriExternalizer;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichTextDataUriExternalizerTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngDataUri(): string
    {
        // 1×1 transparent PNG
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        return 'data:image/png;base64,'.$base64;
    }

    public function test_externalizes_data_uri_on_new_ticket_html(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 9001,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        $html = '<p>See screenshot</p><img src="'.$this->tinyPngDataUri().'" alt="shot">';

        $clean = RichTextDataUriExternalizer::externalize($html, ticket: null, user: $user);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('data:image', $clean);
        $this->assertMatchesRegularExpression('#/storage/helpdesk/rich-text/'.$user->id.'/#', $clean);
        $this->assertLessThan(65000, strlen($clean));
    }

    public function test_externalizes_data_uri_on_ticket_resolution_html(): void
    {
        Storage::fake('public');
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();
        $agent = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $agent->id,
            'staff_id' => 9002,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        $ticket = HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-EXT-0001',
            'category_id' => $cat->id,
            'subject' => 'Image test',
            'description' => '<p>Test</p>',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 9001,
            'assigned_user_id' => $agent->id,
        ]);

        $html = '<p>Fixed</p><img src="'.$this->tinyPngDataUri().'">';

        $clean = RichTextDataUriExternalizer::externalize($html, ticket: $ticket, user: $agent);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('data:image', $clean);
        $this->assertStringContainsString('/api/v1/attachments/', $clean);
        $this->assertDatabaseHas('helpdesk_ticket_attachments', [
            'ticket_id' => $ticket->id,
        ]);
    }
}
