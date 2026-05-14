<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferenceDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_load_reference_data_when_staff_api_is_stubbed(): void
    {
        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.staff_api.token' => 'testtoken',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.reference_data_cache_ttl' => 60,
        ]);

        Http::fake([
            'http://staff.test/share/divisions/testtoken' => Http::response([
                ['division_id' => 21, 'division_name' => 'IT Division', 'directorate_id' => 4],
            ]),
            'http://staff.test/share/directorates/testtoken' => Http::response([
                [
                    'directorate_id' => 4,
                    'name' => 'Operations',
                    'director_id' => 101,
                    'director' => [
                        'id' => 101,
                        'fname' => 'Ada',
                        'lname' => 'Lovelace',
                        'title' => null,
                        'name' => 'Ada Lovelace',
                    ],
                ],
            ]),
            'http://staff.test/share/get_current_staff/testtoken*' => Http::response([
                [
                    'staff_id' => 101,
                    'fname' => 'Ada',
                    'lname' => 'Lovelace',
                    'work_email' => 'ada@example.org',
                    'division_id' => 21,
                ],
            ]),
        ]);

        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 9001,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);
        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/reference-data')
            ->assertOk()
            ->assertJsonPath('data.divisions.0.id', 21)
            ->assertJsonPath('data.directorates.0.id', 4)
            ->assertJsonPath('data.directorates.0.director_id', 101)
            ->assertJsonPath('data.directorates.0.director.name', 'Ada Lovelace');

        $this->getJson('/api/v1/reference-data/staff?division_id=21')
            ->assertOk()
            ->assertJsonPath('data.staff.0.id', 101)
            ->assertJsonPath('data.staff.0.work_email', 'ada@example.org');
    }

    public function test_end_user_with_staff_id_can_load_reference_data_when_staff_api_is_stubbed(): void
    {
        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.staff_api.token' => 'testtoken',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.reference_data_cache_ttl' => 60,
        ]);

        Http::fake([
            'http://staff.test/share/divisions/testtoken' => Http::response([
                ['division_id' => 21, 'division_name' => 'IT Division', 'directorate_id' => 4],
            ]),
            'http://staff.test/share/directorates/testtoken' => Http::response([
                [
                    'directorate_id' => 4,
                    'name' => 'Operations',
                    'director_id' => 101,
                    'director' => [
                        'id' => 101,
                        'fname' => 'Ada',
                        'lname' => 'Lovelace',
                        'title' => null,
                        'name' => 'Ada Lovelace',
                    ],
                ],
            ]),
            'http://staff.test/share/get_current_staff/testtoken*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 8001,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);
        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/reference-data')->assertOk();
        $this->getJson('/api/v1/reference-data/staff')->assertOk();
    }
}
