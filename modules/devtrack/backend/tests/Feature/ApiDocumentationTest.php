<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiDocsAuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_hidden_when_disabled(): void
    {
        putenv('API_DOCS_ENABLED=false');
        $_ENV['API_DOCS_ENABLED'] = 'false';
        $_SERVER['API_DOCS_ENABLED'] = 'false';
        $this->refreshApplication();

        $this->get('/api/documentation')->assertNotFound();
        $this->get('/api/docs.json')->assertNotFound();
    }

    public function test_api_docs_require_authentication(): void
    {
        putenv('API_DOCS_ENABLED=true');
        $_ENV['API_DOCS_ENABLED'] = 'true';
        $_SERVER['API_DOCS_ENABLED'] = 'true';
        $this->refreshApplication();

        $this->get('/api/documentation')->assertRedirect('/login');
        $this->getJson('/api/docs.json')->assertUnauthorized();
    }

    public function test_authenticated_user_can_open_api_docs(): void
    {
        putenv('API_DOCS_ENABLED=true');
        $_ENV['API_DOCS_ENABLED'] = 'true';
        $_SERVER['API_DOCS_ENABLED'] = 'true';
        $this->refreshApplication();

        $user = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->withToken($token)
            ->get('/api/documentation')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $docs = $this->withToken($token)
            ->getJson('/api/docs.json')
            ->assertOk()
            ->assertJsonPath('info.title', 'Email Server API');

        $sendContent = $docs->json('paths./integrations/send.post.requestBody.content');
        $this->assertIsArray($sendContent);
        $this->assertArrayHasKey('application/x-www-form-urlencoded', $sendContent);
        $this->assertArrayHasKey('application/json', $sendContent);

        $formProps = $sendContent['application/x-www-form-urlencoded']['schema']['properties'] ?? [];
        foreach (['to', 'subject', 'body', 'is_html', 'provider_id', 'cc', 'bcc', 'attachments'] as $field) {
            $this->assertArrayHasKey($field, $formProps, "Form schema missing {$field}");
        }

        $this->assertArrayHasKey(
            'attachments',
            $sendContent['application/json']['schema']['properties'] ?? []
        );
        $this->assertSame(
            'object',
            $sendContent['application/json']['schema']['properties']['attachments']['items']['type'] ?? null
        );
        $this->assertArrayHasKey(
            'filename',
            $sendContent['application/json']['schema']['properties']['attachments']['items']['properties'] ?? []
        );
        $this->assertArrayHasKey(
            'content',
            $sendContent['application/json']['schema']['properties']['attachments']['items']['properties'] ?? []
        );
    }

    public function test_docs_auth_cookie_grants_access(): void
    {
        putenv('API_DOCS_ENABLED=true');
        $_ENV['API_DOCS_ENABLED'] = 'true';
        $_SERVER['API_DOCS_ENABLED'] = 'true';
        $this->refreshApplication();

        $user = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->withUnencryptedCookie(ApiDocsAuthCookie::NAME, $token)
            ->get('/api/documentation')
            ->assertOk();

        $this->withUnencryptedCookie(ApiDocsAuthCookie::NAME, $token)
            ->getJson('/api/docs.json')
            ->assertOk();
    }

    public function test_login_sets_docs_auth_cookie(): void
    {
        putenv('API_DOCS_ENABLED=true');
        $_ENV['API_DOCS_ENABLED'] = 'true';
        $_SERVER['API_DOCS_ENABLED'] = 'true';
        $this->refreshApplication();

        $user = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => 'SecurePass123!',
        ]);

        app(\App\Services\CaptchaService::class)->seedForTests('docs-cookie-key', 'DOCS1');

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
            'captcha_key' => 'docs-cookie-key',
            'captcha' => 'DOCS1',
        ]);

        $response->assertOk();
        $response->assertCookie(ApiDocsAuthCookie::NAME);
    }
}