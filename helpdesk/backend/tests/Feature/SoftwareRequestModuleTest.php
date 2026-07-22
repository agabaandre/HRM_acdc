<?php

namespace Tests\Feature;

use App\Mail\SoftwareRequestSubmittedMail;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoftwareRequestModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_fills_org_fields_and_notifies_group_members(): void
    {
        Mail::fake();

        $agent = User::factory()->create(['email' => 'sw-agent@example.com', 'name' => 'SW Agent']);
        HelpdeskProfile::query()->create([
            'user_id' => $agent->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_id' => 501,
        ]);

        $group = HelpdeskSupportGroup::query()->create([
            'name' => 'Software Development',
            'slug' => 'sw-notify-test',
            'sort_order' => 1,
            'is_active' => true,
            'is_system' => false,
        ]);
        $group->members()->sync([$agent->id]);
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS, (string) $group->id);

        $requester = User::factory()->create(['email' => 'req@example.com', 'name' => 'Requester']);
        HelpdeskProfile::query()->create([
            'user_id' => $requester->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 502,
            'division_id' => 21,
            'directorate_id' => 3,
            'can_submit_software_requests' => true,
        ]);

        Sanctum::actingAs($requester->fresh(['helpdeskProfile']));

        $response = $this->postJson('/api/v1/tools/software-requests', [
            'requester_name' => 'Requester',
            'email' => 'req@example.com',
            'request_title' => 'New portal module',
            'priority' => 'high',
            'submit' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'submitted');
        $response->assertJsonPath('data.division_id', 21);
        $response->assertJsonPath('data.directorate_id', 3);

        Mail::assertSent(SoftwareRequestSubmittedMail::class, 1);
    }

    public function test_review_board_member_can_approve_when_board_configured(): void
    {
        $board = User::factory()->create(['email' => 'board@example.com']);
        HelpdeskProfile::query()->create([
            'user_id' => $board->id,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'staff_id' => 601,
            'can_approve_software_requests' => false,
            'can_manage_software_requests' => false,
        ]);
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS, (string) $board->id);

        $requester = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $requester->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 602,
            'can_submit_software_requests' => true,
        ]);

        Sanctum::actingAs($requester->fresh(['helpdeskProfile']));
        $create = $this->postJson('/api/v1/tools/software-requests', [
            'requester_name' => 'R',
            'request_title' => 'Need software',
            'submit' => true,
        ])->assertCreated();

        $id = (int) $create->json('data.id');

        Sanctum::actingAs($board->fresh(['helpdeskProfile']));
        $this->postJson("/api/v1/tools/software-requests/{$id}/approve", [
            'approval_role' => 'review_board',
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_non_owner_cannot_list_others_requests(): void
    {
        $owner = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $owner->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 701,
            'can_submit_software_requests' => true,
        ]);

        $other = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $other->id,
            'role' => HelpdeskProfile::ROLE_USER,
            'staff_id' => 702,
            'can_submit_software_requests' => true,
        ]);

        Sanctum::actingAs($owner->fresh(['helpdeskProfile']));
        $id = (int) $this->postJson('/api/v1/tools/software-requests', [
            'requester_name' => 'Owner',
            'request_title' => 'Mine',
            'submit' => true,
        ])->json('data.id');

        Sanctum::actingAs($other->fresh(['helpdeskProfile']));
        $list = $this->getJson('/api/v1/tools/software-requests')->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertNotContains($id, $ids);
    }
}
