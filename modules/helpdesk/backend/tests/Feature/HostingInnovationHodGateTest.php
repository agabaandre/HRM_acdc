<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HostingInnovationHodGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        cache()->put('helpdesk_reference_bundle_v1', [
            'divisions' => [
                [
                    'id' => 21,
                    'name' => 'Test Division',
                    'short_name' => null,
                    'directorate_id' => 3,
                    'division_head' => 9001,
                    'head_oic_id' => null,
                    'head_oic_start_date' => null,
                    'head_oic_end_date' => null,
                ],
            ],
            'directorates' => [
                [
                    'id' => 3,
                    'name' => 'Test Directorate',
                    'director_id' => null,
                    'director' => null,
                ],
            ],
        ], 3600);

        cache()->put('helpdesk_reference_staff_v1_'.$limit, [
            [
                'staff_id' => 9001,
                'fname' => 'Head',
                'lname' => 'Division',
                'work_email' => 'hod@example.com',
                'division_id' => 21,
                'duty_station_name' => 'HQ',
            ],
            [
                'staff_id' => 9002,
                'fname' => 'Req',
                'lname' => 'User',
                'work_email' => 'req@example.com',
                'division_id' => 21,
                'duty_station_name' => 'HQ',
            ],
        ], 3600);
    }

    public function test_hosting_process_blocked_until_hod_approves(): void
    {
        $hod = User::factory()->create(['email' => 'hod@example.com', 'name' => 'HoD']);
        HelpdeskProfile::query()->create([
            'user_id' => $hod->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 9001,
            'division_id' => 21,
        ]);

        $processor = User::factory()->create(['email' => 'proc@example.com']);
        HelpdeskProfile::query()->create([
            'user_id' => $processor->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_id' => 9010,
            'can_process_hosting_requests' => true,
        ]);

        $requester = User::factory()->create(['email' => 'req@example.com', 'name' => 'Requester']);
        HelpdeskProfile::query()->create([
            'user_id' => $requester->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 9002,
            'division_id' => 21,
        ]);

        Sanctum::actingAs($requester->fresh(['helpdeskProfile']));
        $create = $this->postJson('/api/v1/tools/hosting-requests', [
            'title' => 'Azure app service',
            'category' => 'cloud',
            'cloud_provider' => 'Azure',
            'submit' => true,
        ])->assertCreated();

        $id = (int) $create->json('data.id');
        $this->assertSame('pending_hod', $create->json('data.status'));
        $this->assertSame(9001, (int) $create->json('data.hod_staff_id'));

        Sanctum::actingAs($processor->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/hosting-requests/{$id}/process")
            ->assertForbidden();

        Sanctum::actingAs($hod->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/hosting-requests/{$id}/hod-approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'hod_approved');

        Sanctum::actingAs($processor->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/hosting-requests/{$id}/process")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_admin_cannot_skip_hosting_hod_gate(): void
    {
        $admin = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 9999,
            'grant_helpdesk_admin' => true,
            'can_process_hosting_requests' => true,
        ]);

        $requester = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $requester->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 9002,
            'division_id' => 21,
        ]);

        Sanctum::actingAs($requester->fresh(['helpdeskProfile']));
        $id = (int) $this->postJson('/api/v1/tools/hosting-requests', [
            'title' => 'On prem VM',
            'category' => 'on_premises',
            'submit' => true,
        ])->json('data.id');

        Sanctum::actingAs($admin->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/hosting-requests/{$id}/process")->assertForbidden();
    }

    public function test_innovation_process_without_hod(): void
    {
        $processor = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $processor->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_id' => 9020,
            'can_process_innovation_requests' => true,
        ]);

        $requester = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $requester->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 9002,
            'division_id' => 21,
        ]);

        Sanctum::actingAs($requester->fresh(['helpdeskProfile']));
        $id = (int) $this->postJson('/api/v1/tools/innovation-requests', [
            'title' => 'New idea',
            'submit' => true,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($processor->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/innovation-requests/{$id}/process")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }
}
