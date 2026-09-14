<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSlaRule;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSlaRulesApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900003,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_admin_can_create_and_update_sla_rule(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        Sanctum::actingAs($this->adminUser());
        $cat = HelpdeskCategory::query()->firstOrFail();

        $this->postJson('/api/v1/admin/sla-rules', [
            'name' => 'Default email',
            'category_id' => $cat->id,
            'response_minutes' => 60,
            'resolution_minutes' => 480,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Default email');

        $rule = HelpdeskSlaRule::query()->where('name', 'Default email')->firstOrFail();

        $this->putJson('/api/v1/admin/sla-rules/'.$rule->id, [
            'response_minutes' => 120,
        ])->assertOk()
            ->assertJsonPath('data.response_minutes', 120);
    }
}
