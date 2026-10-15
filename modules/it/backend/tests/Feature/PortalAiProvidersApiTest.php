<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Ai\PortalAiCompatibleClient;
use Modules\Settings\Http\Controllers\Api\V1\PortalAiProvidersController;
use Modules\Settings\Models\PortalAiProvider;
use Modules\Settings\Services\PortalAiProviderService;
use Modules\Settings\Support\PortalAiProvidersConfig;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PortalAiProvidersApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
        ]);
        \Illuminate\Support\Facades\DB::purge();
        \Illuminate\Support\Facades\DB::reconnect();

        Schema::create('portal_ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver', 32);
            $table->string('api_endpoint', 512)->default('');
            $table->string('model', 191)->default('');
            $table->text('api_key')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        session()->put('user.permissions', [15]);
    }

    public function test_driver_presets_include_openai_and_other_models(): void
    {
        $keys = PortalAiProvidersConfig::driverKeys();

        $this->assertContains('openai', $keys);
        $this->assertContains('gemini', $keys);
        $this->assertContains('deepseek', $keys);
        $this->assertContains('grok', $keys);
        $this->assertContains('mistral', $keys);
        $this->assertContains('custom', $keys);

        $openai = PortalAiProvidersConfig::driver('openai');
        $this->assertSame('https://api.openai.com/v1', $openai['api_endpoint'] ?? null);
        $this->assertSame('gpt-4o-mini', $openai['model'] ?? null);
    }

    public function test_index_seeds_openai_as_default_and_redacts_keys(): void
    {
        $response = app(PortalAiProvidersController::class)->index();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame('openai', $payload['data'][0]['driver']);
        $this->assertTrue($payload['data'][0]['is_default']);
        $this->assertArrayNotHasKey('api_key', $payload['data'][0]);
        $this->assertFalse($payload['data'][0]['has_api_key']);
    }

    public function test_cannot_delete_default_provider(): void
    {
        $svc = app(PortalAiProviderService::class);
        $svc->ensureDefaultOpenAi();
        $row = PortalAiProvider::query()->where('slug', 'openai')->firstOrFail();

        $this->expectException(ValidationException::class);
        $svc->delete($row);
    }

    public function test_store_encrypts_api_key(): void
    {
        $request = Request::create('/api/v1/settings/ai-providers', 'POST', [
            'name' => 'Gemini',
            'driver' => 'gemini',
            'api_key' => 'secret-gemini-key',
            'is_default' => false,
        ]);

        $response = app(PortalAiProvidersController::class)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['data']['has_api_key']);
        $this->assertSame('gemini-2.0-flash', $payload['data']['model']);
        $this->assertArrayNotHasKey('api_key', $payload['data']);

        $stored = PortalAiProvider::query()->where('uuid', $payload['data']['uuid'])->firstOrFail();
        $this->assertNotSame('secret-gemini-key', (string) $stored->api_key);
        $this->assertSame('secret-gemini-key', Crypt::decryptString((string) $stored->api_key));
    }

    public function test_connection_probe_succeeds_with_http_fake(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ], 200),
        ]);

        $svc = app(PortalAiProviderService::class);
        $svc->ensureDefaultOpenAi();
        $row = PortalAiProvider::query()->where('slug', 'openai')->firstOrFail();
        $row->update(['api_key' => Crypt::encryptString('sk-test')]);

        $result = app(PortalAiCompatibleClient::class)->testConnection($row->fresh());

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['reply_preview']);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/chat/completions')
                && ($request['model'] ?? null) === 'gpt-4o-mini';
        });
    }

    public function test_admin_index_requires_settings_permission(): void
    {
        session()->put('user.permissions', []);

        $this->expectException(HttpException::class);
        app(PortalAiProvidersController::class)->index();
    }

    public function test_ai_api_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertContains('api/v1/settings/ai-providers', $uris);
        $this->assertContains('api/v1/settings/ai-providers/drivers', $uris);
        $this->assertContains('api/v1/settings/ai-providers/test', $uris);
        $this->assertContains('api/v1/settings/languages/translations/ai', $uris);
    }
}
