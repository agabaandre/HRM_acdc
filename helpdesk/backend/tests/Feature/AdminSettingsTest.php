<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900001,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_non_admin_cannot_view_settings(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 1,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);
        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_admin_can_view_and_update_branding(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.ai_provider', 'openai');

        $this->putJson('/api/v1/admin/settings', [
            'branding_primary_hex' => '#112233',
            'branding_secondary_hex' => '#445566',
            'ai_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.branding_primary_hex', '#112233');

        $this->assertSame('#112233', HelpdeskSetting::getValue(HelpdeskSetting::KEY_BRANDING_PRIMARY));
    }
}
