<?php

namespace Tests\Feature;

use App\Models\HelpdeskAuditLog;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create(['name' => 'Audit Admin']);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900011,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_admin_can_list_audit_logs_with_staff_name(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $admin = $this->adminUser();

        HelpdeskAuditLog::query()->create([
            'user_id' => $admin->id,
            'staff_id' => 900011,
            'action' => 'test.seed',
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'correlation_id' => null,
            'old_values' => null,
            'new_values' => ['note' => 'seed'],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'test.seed')
            ->assertJsonPath('data.0.staff_name', 'Audit Admin')
            ->assertJsonPath('meta.stats.total', 1);
    }

    public function test_admin_can_filter_audit_logs_by_action(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        HelpdeskAuditLog::query()->create([
            'user_id' => $admin->id,
            'staff_id' => 900011,
            'action' => 'kb_article.created',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        HelpdeskAuditLog::query()->create([
            'user_id' => $admin->id,
            'staff_id' => 900011,
            'action' => 'reference_data.sync',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->getJson('/api/v1/admin/audit-logs?action=kb_article.created')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'kb_article.created');
    }

    public function test_admin_can_reverse_kb_article_created_log(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $admin = $this->adminUser();
        $cat = HelpdeskCategory::query()->firstOrFail();

        $article = HelpdeskKbArticle::query()->create([
            'category_id' => $cat->id,
            'question' => 'Test Q',
            'answer' => '<p>A</p>',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $log = HelpdeskAuditLog::query()->create([
            'user_id' => $admin->id,
            'staff_id' => 900011,
            'action' => 'kb_article.created',
            'auditable_type' => HelpdeskKbArticle::class,
            'auditable_id' => $article->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'old_values' => null,
            'new_values' => ['id' => $article->id, 'question' => 'Test Q'],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/audit-logs/{$log->id}/reverse", [
            'action_type' => 'delete',
            'reason' => 'Undo mistaken KB article creation during testing.',
        ])->assertOk();

        $this->assertDatabaseMissing('helpdesk_kb_articles', ['id' => $article->id]);
        $this->assertDatabaseHas('helpdesk_audit_logs', [
            'action' => 'audit.reversed',
            'auditable_id' => $log->id,
        ]);
    }
}
