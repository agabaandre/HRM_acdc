<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Models\PortalLanguage;
use Modules\Settings\Services\PortalLanguageService;
use Modules\Settings\Services\PortalUiTranslationService;
use Tests\TestCase;

class PortalLanguageServiceTest extends TestCase
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
    }

    public function test_seeds_all_au_working_languages(): void
    {
        $svc = app(PortalLanguageService::class);
        $svc->seedAuLanguages();

        $codes = PortalLanguage::query()->orderBy('sort_order')->pluck('locale_code')->all();

        $this->assertSame(['en', 'fr', 'ar', 'es', 'pt', 'sw'], $codes);
        $this->assertSame(['en', 'fr', 'ar', 'es', 'pt', 'sw'], PortalLanguage::activeLocaleCodes());
    }

    public function test_inactive_language_is_hidden_from_selector_but_kept_for_translation_editor(): void
    {
        $svc = app(PortalLanguageService::class);
        $svc->seedAuLanguages();
        $sw = PortalLanguage::query()->where('locale_code', 'sw')->firstOrFail();
        $svc->update($sw, [
            'name' => 'Kiswahili',
            'is_active' => false,
            'sort_order' => 60,
        ]);

        $this->assertNotContains('sw', PortalLanguage::activeLocaleCodes());
        $this->assertContains('sw', PortalLanguage::allLocaleCodes());
        $this->assertArrayNotHasKey('sw', PortalLanguage::selectorMap());
    }

    public function test_cannot_delete_english(): void
    {
        $svc = app(PortalLanguageService::class);
        $svc->seedAuLanguages();
        $en = PortalLanguage::query()->where('locale_code', 'en')->firstOrFail();

        $this->expectException(ValidationException::class);
        $svc->delete($en);
    }

    public function test_nav_translations_fall_back_to_au_defaults_then_english(): void
    {
        $svc = app(PortalLanguageService::class);
        $svc->seedAuLanguages();
        $ui = app(PortalUiTranslationService::class);

        $fr = $ui->loadMerged('fr', 'nav');
        $this->assertSame('Tableau de bord', $fr['dashboard']);
        $this->assertSame('Congés', $fr['leave']);

        $en = $ui->loadMerged('en', 'nav');
        $this->assertSame('Dashboard', $en['dashboard']);

        $ui->saveGroup('fr', 'nav', ['dashboard' => 'Accueil RH', 'leave' => 'Congés']);
        $frSaved = $ui->loadMerged('fr', 'nav');
        $this->assertSame('Accueil RH', $frSaved['dashboard']);
        $this->assertSame('Congés', $frSaved['leave']);
        $this->assertSame('Personnel', $frSaved['staff']);
    }

    public function test_profile_locale_wins_over_cookie(): void
    {
        $svc = app(PortalLanguageService::class);
        $svc->seedAuLanguages();

        $this->assertSame('ar', $svc->resolveActiveLocale('ar', 'fr'));
        $this->assertSame('fr', $svc->resolveActiveLocale(null, 'fr'));
        $this->assertSame('en', $svc->resolveActiveLocale('xx', 'yy'));
    }

    public function test_catalog_includes_rtl_and_menu_translations(): void
    {
        $svc = app(PortalLanguageService::class);
        $catalog = $svc->catalog('ar', null);

        $this->assertSame('ar', $catalog['locale']);
        $this->assertSame('rtl', $catalog['direction']);
        $this->assertTrue($catalog['is_rtl']);
        $this->assertSame('لوحة المعلومات', $catalog['translations']['nav']['dashboard']);
        $this->assertCount(6, $catalog['languages']);
    }

    public function test_every_au_language_has_all_menu_and_chrome_keys(): void
    {
        $cfg = require dirname(__DIR__, 2).'/Modules/Settings/config/supported_locales.php';
        $english = $cfg['english'];
        $this->assertArrayHasKey('nav', $english);
        $this->assertArrayHasKey('chrome', $english);

        foreach (['fr', 'ar', 'es', 'pt', 'sw'] as $locale) {
            foreach ($english as $group => $keys) {
                foreach (array_keys($keys) as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $cfg['default_translations'][$locale][$group] ?? [],
                        "Missing {$locale}.{$group}.{$key}",
                    );
                    $this->assertNotSame(
                        '',
                        trim((string) ($cfg['default_translations'][$locale][$group][$key] ?? '')),
                        "Empty {$locale}.{$group}.{$key}",
                    );
                }
            }
        }
    }

    public function test_extra_submenu_and_button_catalog_is_complete(): void
    {
        $cfg = require dirname(__DIR__, 2).'/Modules/Settings/config/supported_locales_menus.php';
        $english = $cfg['english'];
        $this->assertArrayHasKey('subnav', $english);
        $this->assertArrayHasKey('actions', $english);
        $this->assertArrayHasKey('settings', $english);

        foreach (['fr', 'ar', 'es', 'pt', 'sw'] as $locale) {
            foreach ($english as $group => $keys) {
                foreach (array_keys($keys) as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $cfg['default_translations'][$locale][$group] ?? [],
                        "Missing extra {$locale}.{$group}.{$key}",
                    );
                    $this->assertNotSame(
                        '',
                        trim((string) ($cfg['default_translations'][$locale][$group][$key] ?? '')),
                        "Empty extra {$locale}.{$group}.{$key}",
                    );
                }
            }
        }
    }
}
