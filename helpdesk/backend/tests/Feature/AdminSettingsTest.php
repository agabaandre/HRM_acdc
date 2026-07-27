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

    public function test_admin_can_test_ai_configuration(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        HelpdeskSetting::setValue(HelpdeskSetting::KEY_AI_API_ENDPOINT, 'https://api.openai.com/v1');
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_AI_MODEL_NAME, 'gpt-4o-mini');
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_AI_ACTIVE, '1');
        HelpdeskSetting::setValue(
            HelpdeskSetting::KEY_AI_API_KEY,
            \Illuminate\Support\Facades\Crypt::encryptString('sk-test-key')
        );

        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/admin/settings/test-ai')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.key_present', true)
            ->assertJsonPath('data.ai_active', true);
    }

    public function test_ai_test_fails_without_api_key(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());

        HelpdeskSetting::setValue(HelpdeskSetting::KEY_AI_API_KEY, '');

        $this->postJson('/api/v1/admin/settings/test-ai')
            ->assertStatus(422)
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.key_present', false);
    }
}
