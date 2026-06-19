<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpdeskAskTest extends TestCase
{
    use RefreshDatabase;

    private function endUser(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 1001,
            'role' => HelpdeskProfile::ROLE_USER,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_ask_returns_fallback_when_ai_is_off(): void
    {
        Sanctum::actingAs($this->endUser());

        $response = $this->postJson('/api/v1/ai/ask', [
            'question' => 'I cannot connect to the VPN from home',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.suggest_ticket', true);
    }

    public function test_ask_uses_knowledge_base_when_article_matches(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $category = HelpdeskCategory::query()->firstOrFail();

        HelpdeskKbArticle::query()->create([
            'category_id' => $category->id,
            'question' => 'How do I reset my password?',
            'answer' => '1. Open the Staff portal. 2. Click Forgot password. 3. Follow the email link.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->endUser());

        $response = $this->postJson('/api/v1/ai/ask', [
            'question' => 'I forgot my password and cannot sign in',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'knowledge_base')
            ->assertJsonStructure(['data' => ['answer', 'steps', 'related_articles']]);
    }

    public function test_ask_requires_authentication(): void
    {
        $this->postJson('/api/v1/ai/ask', ['question' => 'Printer not working today'])
            ->assertUnauthorized();
    }
}
