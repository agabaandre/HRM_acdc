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

class KbArticleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function kbManager(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 900022,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'can_manage_kb' => true,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_kb_article_delete_writes_audit_log(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $user = $this->kbManager();
        $category = HelpdeskCategory::query()->firstOrFail();

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/admin/kb/articles', [
            'category_id' => $category->id,
            'question' => 'How to test audit?',
            'answer' => '<p>Steps here</p>',
            'sort_order' => 1,
            'is_active' => true,
        ])->assertCreated();

        $articleId = (int) $create->json('data.id');

        $this->deleteJson('/api/v1/admin/kb/articles/'.$articleId)
            ->assertOk();

        $this->assertDatabaseMissing('helpdesk_kb_articles', ['id' => $articleId]);

        $log = HelpdeskAuditLog::query()
            ->where('action', 'kb_article.deleted')
            ->where('auditable_id', $articleId)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('How to test audit?', $log->old_values['question'] ?? null);
        $this->assertNull($log->new_values);
    }
}
