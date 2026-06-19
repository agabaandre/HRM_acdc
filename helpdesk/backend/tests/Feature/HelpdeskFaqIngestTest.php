<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpdeskFaqIngestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
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

    public function test_faq_ingest_creates_kb_articles_from_apm_export(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);

        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.apm_base_url' => 'http://apm.test/staff/apm',
        ]);

        Http::fake([
            'http://apm.test/staff/apm/api/apm/v1/faqs/export' => Http::response([
                'data' => [
                    'source' => 'apm',
                    'source_url' => 'http://apm.test/staff/apm/faq',
                    'faqs' => [
                        [
                            'external_id' => 'apm:faq:1',
                            'category_slug' => 'staff-portal',
                            'question' => 'How do I reset my password?',
                            'answer_html' => '<p>Use Forgot password on the login page.</p>',
                            'search_keywords' => 'password login',
                            'sort_order' => 1,
                            'is_active' => true,
                        ],
                    ],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/v1/admin/faq-ingest');

        $response->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('helpdesk_kb_articles', [
            'external_id' => 'apm:faq:1',
            'source' => 'apm',
            'question' => 'How do I reset my password?',
        ]);

        $category = HelpdeskCategory::query()->where('slug', 'staff-portal')->first();
        $article = HelpdeskKbArticle::query()->where('external_id', 'apm:faq:1')->first();
        $this->assertNotNull($category);
        $this->assertSame($category->id, $article->category_id);
    }
}
