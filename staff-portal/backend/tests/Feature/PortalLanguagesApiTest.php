<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\PortalUser;
use Modules\Settings\Http\Controllers\Api\V1\PortalLanguagesController;
use Modules\Settings\Models\PortalLanguage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PortalLanguagesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
            'settings.supported_locales' => require dirname(__DIR__, 2).'/Modules/Settings/config/supported_locales.php',
        ]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        PortalLanguage::flushCache();

        Schema::create('portal_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('locale_code', 32)->unique();
            $table->string('name', 120);
            $table->string('google_translate_code', 32)->nullable();
            $table->string('flag_emoji', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('portal_ui_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('locale_code', 32);
            $table->string('group_key', 64);
            $table->string('item_key', 120);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['locale_code', 'group_key', 'item_key']);
        });

        Schema::create('user', function (Blueprint $table): void {
            $table->integer('user_id')->primary();
            $table->string('name')->nullable();
            $table->string('langauge')->nullable();
            $table->unsignedInteger('role')->nullable();
            $table->unsignedInteger('auth_staff_id')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('allow_email_login')->default(false);
        });

        DB::table('user')->insert([
            'user_id' => 1,
            'name' => 'Test User',
            'langauge' => 'en',
            'role' => 10,
            'auth_staff_id' => 100,
            'status' => true,
            'allow_email_login' => false,
        ]);

        session()->put('user.permissions', [15]);
    }

    public function test_language_api_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertContains('api/v1/languages', $uris);
        $this->assertContains('api/v1/locale', $uris);
        $this->assertContains('api/v1/settings/languages', $uris);
        $this->assertContains('api/v1/settings/languages/translations', $uris);
        $this->assertContains('api/v1/settings/languages/translations/ai', $uris);
    }

    public function test_catalog_returns_all_au_working_languages(): void
    {
        $user = PortalUser::query()->findOrFail(1);
        $request = Request::create('/api/v1/languages', 'GET');
        $request->setUserResolver(static fn () => $user);

        $response = app(PortalLanguagesController::class)->catalog($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['en', 'fr', 'ar', 'es', 'pt', 'sw'], array_column($payload['data']['languages'], 'code'));
        $this->assertSame('en', $payload['data']['locale']);
        $this->assertSame('Dashboard', $payload['data']['translations']['nav']['dashboard']);
        $this->assertSame('Language', $payload['data']['translations']['chrome']['language']);
    }

    public function test_apply_persists_profile_locale_and_returns_menu_translations(): void
    {
        $user = PortalUser::query()->findOrFail(1);
        $request = Request::create('/api/v1/locale', 'POST', ['locale' => 'pt']);
        $request->setUserResolver(static fn () => $user);

        $response = app(PortalLanguagesController::class)->apply($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pt', $payload['data']['locale']);
        $this->assertSame('Painel', $payload['data']['translations']['nav']['dashboard']);
        $this->assertSame('pt', DB::table('user')->where('user_id', 1)->value('langauge'));
    }

    public function test_admin_index_lists_seeded_languages(): void
    {
        $response = app(PortalLanguagesController::class)->index();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['en', 'fr', 'ar', 'es', 'pt', 'sw'],
            array_column($payload['data']['languages'], 'locale_code'),
        );
        $this->assertArrayHasKey('nav', $payload['data']['groups']);
        $this->assertArrayHasKey('chrome', $payload['data']['groups']);
    }

    public function test_admin_index_requires_settings_permission(): void
    {
        session()->put('user.permissions', []);

        $this->expectException(HttpException::class);
        app(PortalLanguagesController::class)->index();
    }

    public function test_translation_editor_returns_au_defaults_for_swahili(): void
    {
        $request = Request::create('/api/v1/settings/languages/translations?locale=sw&group=nav', 'GET');

        $response = app(PortalLanguagesController::class)->translations($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('sw', $payload['data']['locale']);
        $this->assertSame('Dashibodi', $payload['data']['lines']['dashboard']);
        $this->assertSame('Dashboard', $payload['data']['english']['dashboard']);
    }
}
