<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
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

    public function test_admin_can_trigger_reference_sync(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        config([
            'helpdesk.staff_api.username' => 'u',
            'helpdesk.staff_api.password' => 'p',
            'helpdesk.staff_api.token' => 'tok',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.staff_api.endpoints.divisions' => '/share/divisions',
            'helpdesk.staff_api.endpoints.directorates' => '/share/directorates',
            'helpdesk.staff_api.endpoints.staff' => '/share/get_current_staff',
        ]);

        Http::fake([
            'http://staff.test/share/divisions/tok' => Http::response([
                ['division_id' => 1, 'division_name' => 'ICT', 'directorate_id' => 10],
            ], 200),
            'http://staff.test/share/directorates/tok' => Http::response([
                ['directorate_id' => 10, 'name' => 'OPS'],
            ], 200),
            'http://staff.test/share/get_current_staff/tok*' => Http::response([
                ['staff_id' => 1, 'fname' => 'Ada', 'lname' => 'Lovelace', 'work_email' => 'ada@example.test', 'division_id' => 1],
            ], 200),
        ]);

        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/v1/admin/reference-sync')
            ->assertOk()
            ->assertJsonPath('data.divisions', 1)
            ->assertJsonPath('data.directorates', 1)
            ->assertJsonPath('data.staff_rows', 1);
    }
}
