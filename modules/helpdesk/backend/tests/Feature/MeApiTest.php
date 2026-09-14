<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Me Tester',
            'photo' => 'portrait.jpg',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 424242,
            'role' => HelpdeskProfile::ROLE_AGENT,
            'synced_at' => now(),
        ]);

        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $res = $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.profile.role', 'agent')
            ->assertJsonPath('data.profile.staff_id', 424242)
            ->assertJsonPath('data.profile.sap_no', null)
            ->assertJsonPath('data.profile.duty_station', null);

        $url = $res->json('data.avatar_url');
        $this->assertIsString($url);
        $this->assertStringStartsWith('/api/v1/avatar/'.$user->id, $url);
        $this->assertStringContainsString('exp=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function test_me_includes_sap_no_when_set_on_profile(): void
    {
        $user = User::factory()->create(['name' => 'SAP Me']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 111222,
            'sap_no' => 'SAP-UNIT-9',
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        Sanctum::actingAs($user->fresh(['helpdeskProfile']));

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.sap_no', 'SAP-UNIT-9')
            ->assertJsonPath('data.profile.staff_id', 111222);
    }
}
