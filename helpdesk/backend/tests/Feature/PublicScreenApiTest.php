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

    public function test_resolved_today_counts_agent_closed_tickets(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $now = now();

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009902',
            'category_id' => $cat->id,
            'subject' => 'Closed by agent',
            'description' => 'Fixed',
            'priority' => 'low',
            'status' => 'closed',
            'source' => 'web',
            'requester_staff_id' => 99201,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'resolved_at' => $now,
            'closed_at' => $now,
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.volumes.resolved_today', 1);
        $response->assertJsonPath('data.volumes.closed_today', 1);
    }

    public function test_screen_groups_open_tickets_by_requester_duty_station(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $addisUser = $this->helpdeskUser(99301);
        HelpdeskProfile::query()->where('user_id', $addisUser->id)->update([
            'duty_station' => 'Addis Ababa',
        ]);

        $joburgUser = $this->helpdeskUser(99302);
        HelpdeskProfile::query()->where('user_id', $joburgUser->id)->update([
            'duty_station' => 'Johannesburg',
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009903',
            'category_id' => $cat->id,
            'subject' => 'VPN Addis',
            'description' => 'Cannot connect',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 99301,
            'requester_name' => $addisUser->name,
            'requester_email' => $addisUser->email,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009904',
            'category_id' => $cat->id,
            'subject' => 'Laptop Addis',
            'description' => 'Slow device',
            'priority' => 'low',
            'status' => 'in_progress',
            'source' => 'web',
            'requester_staff_id' => 99301,
            'requester_name' => $addisUser->name,
            'requester_email' => $addisUser->email,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009905',
            'category_id' => $cat->id,
            'subject' => 'Email Joburg',
            'description' => 'Mailbox full',
            'priority' => 'high',
            'status' => 'pending',
            'source' => 'web',
            'requester_staff_id' => 99302,
            'requester_name' => $joburgUser->name,
            'requester_email' => $joburgUser->email,
            'sla_resolution_due_at' => now()->subDay(),
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009906',
            'category_id' => $cat->id,
            'subject' => 'Closed Addis',
            'description' => 'Fixed',
            'priority' => 'low',
            'status' => 'closed',
            'source' => 'web',
            'requester_staff_id' => 99301,
            'requester_name' => $addisUser->name,
            'requester_email' => $addisUser->email,
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.by_duty_station.0.name', 'Addis Ababa');
        $response->assertJsonPath('data.by_duty_station.0.open', 2);
        $response->assertJsonPath('data.volumes.in_progress', 1);
        $response->assertJsonPath('data.by_duty_station.0.closed_this_week', 1);
        $response->assertJsonPath('data.by_duty_station.0.overtime', 0);
        $response->assertJsonPath('data.by_duty_station.1.name', 'Johannesburg');
        $response->assertJsonPath('data.by_duty_station.1.open', 1);
        $response->assertJsonPath('data.by_duty_station.1.overtime', 1);
    }

    public function test_screen_uses_staff_directory_duty_station_when_profile_field_empty(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $this->seedHelpdeskStaffDirectoryCache(99501, dutyStationName: 'Nairobi');

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009920',
            'category_id' => $cat->id,
            'subject' => 'Network Nairobi',
            'description' => 'Offline',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 99501,
            'requester_name' => 'Nairobi Staff',
            'requester_email' => 'nairobi@example.org',
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009921',
            'category_id' => $cat->id,
            'subject' => 'Closed Nairobi',
            'description' => 'Fixed',
            'priority' => 'low',
            'status' => 'closed',
            'source' => 'web',
            'requester_staff_id' => 99501,
            'requester_name' => 'Nairobi Staff',
            'requester_email' => 'nairobi@example.org',
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.by_duty_station.0.name', 'Nairobi');
        $response->assertJsonPath('data.by_duty_station.0.open', 1);
        $response->assertJsonPath('data.by_duty_station.0.closed_this_week', 1);
    }

    public function test_screen_uses_requester_duty_station_from_ticket_meta(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009930',
            'category_id' => $cat->id,
            'subject' => 'Closed Accra',
            'description' => 'Fixed',
            'priority' => 'low',
            'status' => 'closed',
            'source' => 'web',
            'requester_staff_id' => 99601,
            'requester_name' => 'Accra Staff',
            'requester_email' => 'accra@example.org',
            'resolved_at' => now(),
            'closed_at' => now(),
            'meta' => ['requester_duty_station' => 'Accra'],
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.by_duty_station.0.name', 'Accra');
        $response->assertJsonPath('data.by_duty_station.0.closed_this_week', 1);
    }

    public function test_screen_lists_agent_closures_for_current_month(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agentA = $this->helpdeskUser(99401, HelpdeskProfile::ROLE_AGENT);
        $agentB = $this->helpdeskUser(99402, HelpdeskProfile::ROLE_AGENT);

        foreach ([
            ['HD-2026-009910', $agentA->id],
            ['HD-2026-009911', $agentA->id],
            ['HD-2026-009912', $agentB->id],
        ] as [$number, $resolverId]) {
            HelpdeskTicket::query()->create([
                'ticket_number' => $number,
                'category_id' => $cat->id,
                'subject' => 'Resolved ticket',
                'description' => 'Done',
                'priority' => 'low',
                'status' => 'closed',
                'source' => 'web',
                'requester_staff_id' => 99499,
                'requester_name' => 'Requester',
                'requester_email' => 'req@example.org',
                'resolved_by_user_id' => $resolverId,
                'resolved_at' => now(),
                'closed_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.closures_by_agent_month.0.name', $agentA->name);
        $response->assertJsonPath('data.closures_by_agent_month.0.closed', 2);
        $response->assertJsonPath('data.closures_by_agent_month.1.name', $agentB->name);
        $response->assertJsonPath('data.closures_by_agent_month.1.closed', 1);
    }

    public function test_screen_lists_in_progress_workload_by_agent(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $agentA = $this->helpdeskUser(99701, HelpdeskProfile::ROLE_AGENT);
        $agentB = $this->helpdeskUser(99702, HelpdeskProfile::ROLE_AGENT);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009940',
            'category_id' => $cat->id,
            'subject' => 'VPN',
            'description' => 'Slow',
            'priority' => 'medium',
            'status' => 'in_progress',
            'source' => 'web',
            'requester_staff_id' => 99799,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009941',
            'category_id' => $cat->id,
            'subject' => 'Laptop',
            'description' => 'Broken',
            'priority' => 'high',
            'status' => 'in_progress',
            'source' => 'web',
            'requester_staff_id' => 99799,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentA->id,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009942',
            'category_id' => $cat->id,
            'subject' => 'Email',
            'description' => 'Sync',
            'priority' => 'low',
            'status' => 'in_progress',
            'source' => 'web',
            'requester_staff_id' => 99799,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $agentB->id,
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonPath('data.in_progress_workload.0.name', $agentA->name);
        $response->assertJsonPath('data.in_progress_workload.0.in_progress', 2);
        $response->assertJsonPath('data.in_progress_workload.1.name', $agentB->name);
        $response->assertJsonPath('data.in_progress_workload.1.in_progress', 1);
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

    public function test_screen_includes_agent_of_week_and_month(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $fastAgent = $this->helpdeskUser(99501, HelpdeskProfile::ROLE_AGENT);
        $slowAgent = $this->helpdeskUser(99502, HelpdeskProfile::ROLE_AGENT);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009920',
            'category_id' => $cat->id,
            'subject' => 'Fast',
            'description' => 'Quick',
            'priority' => 'low',
            'status' => 'resolved',
            'source' => 'web',
            'requester_staff_id' => 99599,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $fastAgent->id,
            'resolved_by_user_id' => $fastAgent->id,
            'first_response_at' => now()->subMinutes(5),
            'resolved_at' => now(),
            'created_at' => now()->subMinutes(10),
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009921',
            'category_id' => $cat->id,
            'subject' => 'Slow',
            'description' => 'Later',
            'priority' => 'low',
            'status' => 'resolved',
            'source' => 'web',
            'requester_staff_id' => 99599,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $slowAgent->id,
            'resolved_by_user_id' => $slowAgent->id,
            'first_response_at' => now()->subMinutes(50),
            'resolved_at' => now(),
            'created_at' => now()->subMinutes(60),
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009922',
            'category_id' => $cat->id,
            'subject' => 'Extra',
            'description' => 'More volume',
            'priority' => 'low',
            'status' => 'resolved',
            'source' => 'web',
            'requester_staff_id' => 99599,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $slowAgent->id,
            'resolved_by_user_id' => $slowAgent->id,
            'first_response_at' => now()->subMinutes(40),
            'resolved_at' => now(),
            'created_at' => now()->subMinutes(45),
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'agent_of_week' => ['period_label', 'weights', 'agent'],
                'agent_of_month' => ['period_label', 'weights', 'agent'],
                'screen' => [
                    'duty_station_items_per_page',
                    'category_items_per_page',
                    'list_slider_interval_seconds',
                    'support_group_slider_interval_seconds',
                ],
            ],
        ]);
        $response->assertJsonPath('data.agent_of_week.weights.tickets', 60);
        $response->assertJsonPath('data.agent_of_week.weights.response', 40);
        // Slow agent has more tickets; fast agent has better response — with 60/40 weighting volume wins.
        $response->assertJsonPath('data.agent_of_week.agent.name', $slowAgent->name);
        $response->assertJsonPath('data.agent_of_week.agent.tickets_worked', 2);
        $response->assertJsonPath('data.agent_of_month.agent.name', $slowAgent->name);
    }

    public function test_screen_includes_agent_of_week_per_support_group(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $infraAgent = $this->helpdeskUser(99601, HelpdeskProfile::ROLE_AGENT);
        $appsAgent = $this->helpdeskUser(99602, HelpdeskProfile::ROLE_AGENT);

        $infraGroup = \App\Models\HelpdeskSupportGroup::query()->create([
            'name' => 'Infrastructure',
            'slug' => 'infrastructure',
            'sort_order' => 1,
            'is_active' => true,
            'is_system' => false,
        ]);
        $infraGroup->members()->sync([$infraAgent->id]);

        $appsGroup = \App\Models\HelpdeskSupportGroup::query()->create([
            'name' => 'Applications',
            'slug' => 'applications',
            'sort_order' => 2,
            'is_active' => true,
            'is_system' => false,
        ]);
        $appsGroup->members()->sync([$appsAgent->id]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009950',
            'category_id' => $cat->id,
            'subject' => 'Server down',
            'description' => 'Outage',
            'priority' => 'high',
            'status' => 'open',
            'source' => 'web',
            'requester_staff_id' => 99699,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_group_id' => $infraGroup->id,
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009951',
            'category_id' => $cat->id,
            'subject' => 'VPN',
            'description' => 'Fixed',
            'priority' => 'medium',
            'status' => 'resolved',
            'source' => 'web',
            'requester_staff_id' => 99699,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $infraAgent->id,
            'assigned_group_id' => $infraGroup->id,
            'resolved_by_user_id' => $infraAgent->id,
            'first_response_at' => now()->subMinutes(10),
            'resolved_at' => now(),
            'created_at' => now()->subMinutes(15),
        ]);

        HelpdeskTicket::query()->create([
            'ticket_number' => 'HD-2026-009952',
            'category_id' => $cat->id,
            'subject' => 'App bug',
            'description' => 'Crash',
            'priority' => 'low',
            'status' => 'resolved',
            'source' => 'web',
            'requester_staff_id' => 99699,
            'requester_name' => 'Requester',
            'requester_email' => 'req@example.org',
            'assigned_user_id' => $appsAgent->id,
            'assigned_group_id' => $appsGroup->id,
            'resolved_by_user_id' => $appsAgent->id,
            'first_response_at' => now()->subMinutes(5),
            'resolved_at' => now(),
            'created_at' => now()->subMinutes(8),
        ]);

        $response = $this->getJson('/api/v1/public/screen');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'priority_matrix_by_group' => [
                    '*' => [
                        'group' => ['id', 'name', 'slug'],
                        'by_priority' => ['urgent', 'high', 'medium', 'low'],
                        'agent_of_week' => ['period_label', 'weights', 'agent'],
                    ],
                ],
            ],
        ]);

        $matrix = collect($response->json('data.priority_matrix_by_group'));
        $infraRow = $matrix->firstWhere('group.slug', 'infrastructure');
        $appsRow = $matrix->firstWhere('group.slug', 'applications');

        $this->assertNotNull($infraRow);
        $this->assertNotNull($appsRow);
        $this->assertSame(1, $infraRow['by_priority']['high']);
        $this->assertSame($infraAgent->name, $infraRow['agent_of_week']['agent']['name']);
        $this->assertSame($appsAgent->name, $appsRow['agent_of_week']['agent']['name']);
    }
}
