<?php

namespace Tests\Feature;

use App\Enums\UserApprovalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        return $admin->createToken('admin-panel')->plainTextToken;
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->withToken($this->adminToken())->postJson('/api/v1/admin/users', [
            'name' => 'Ops User',
            'email' => 'ops@emailserver.local',
            'password' => 'SecurePass123!',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ops@emailserver.local')
            ->assertJsonPath('data.registration_source', 'system')
            ->assertJsonPath('data.external_integration_ids', []);

        $this->assertDatabaseHas('users', [
            'email' => 'ops@emailserver.local',
            'registration_source' => 'system',
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/users/'.$user->id, ['name' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.name', 'After');
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/users/'.$admin->id)
            ->assertStatus(422);
    }

    public function test_admin_can_delete_rejected_account_only(): void
    {
        $token = $this->adminToken();
        $approved = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
        ]);
        $rejected = User::factory()->create([
            'is_admin' => false,
            'is_active' => false,
            'approval_status' => UserApprovalStatus::Rejected,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/users/'.$approved->id)
            ->assertStatus(422);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/users/'.$rejected->id)
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $rejected->id]);
        $this->assertDatabaseHas('users', ['id' => $approved->id]);
    }
}
