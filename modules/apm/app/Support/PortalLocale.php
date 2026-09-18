<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AU locale catalog for APM (shared cookie with Staff Portal / Risk Register).
 * Languages & UI strings can be managed in Staff Portal Settings → Languages
 * (portal_languages / portal_ui_translations via STAFF_DB).
 */
final class PortalLocale
{
    /** @var array<string, mixed>|null */
    private static ?array $mergedConfig = null;

    /** @var array<string, array<string, string>>|null */
    private static ?array $staffDbTranslations = null;

    /** @var array<string, array{code: string, name: string, flag: string, google_code: string, sort_order: int, is_rtl: bool}>|null */
    private static ?array $staffDbLanguages = null;

    public static function config(): array
    {
        if (self::$mergedConfig !== null) {
            return self::$mergedConfig;
        }

        /** @var array<string, mixed> $cfg */
        $cfg = config('supported_locales', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $extraPath = config_path('supported_locales_pending_approvals.php');
        if (is_file($extraPath)) {
            $extra = require $extraPath;
            if (is_array($extra)) {
                $cfg = array_replace_recursive($cfg, $extra);
            }
        }

        self::$mergedConfig = $cfg;

        return self::$mergedConfig;
    }

    public static function cookieName(): string
    {
        return (string) (self::config()['cookie'] ?? 'staff_portal_locale');
    }

    public static function cookieMinutes(): int
    {
        return (int) (self::config()['cookie_minutes'] ?? 525600);
    }

    public static function defaultLocale(): string
    {
        return (string) (self::config()['default'] ?? 'en');
    }

    /**
     * @return list<string>
     */
    public static function rtlLocales(): array
    {
        $rtl = self::config()['rtl_locales'] ?? ['ar'];

        return array_values(array_map('strval', (array) $rtl));
    }

    public static function isRtl(?string $locale = null): bool
    {
        $locale = $locale ?? App::getLocale();

        return in_array($locale, self::rtlLocales(), true);
    }

    /**
     * @return array<string, array{code: string, name: string, flag: string, google_code: string, sort_order: int, is_rtl: bool}>
     */
    public static function languages(): array
    {
        $fromDb = self::languagesFromStaffDb();
        if ($fromDb !== []) {
            return $fromDb;
        }

        $out = [];
        foreach ((array) (self::config()['languages'] ?? []) as $code => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $code = strtolower((string) $code);
            $out[$code] = [
                'code' => $code,
                'name' => (string) ($meta['name'] ?? strtoupper($code)),
                'flag' => (string) ($meta['flag'] ?? ''),
                'google_code' => (string) ($meta['google_code'] ?? $code),
                'sort_order' => (int) ($meta['sort_order'] ?? 100),
                'is_rtl' => self::isRtl($code),
            ];
        }
        uasort($out, static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $out;
    }

    public static function isSupported(string $locale): bool
    {
        return array_key_exists(strtolower(trim($locale)), self::languages());
    }

    public static function normalize(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale !== '' && self::isSupported($locale)) {
            return $locale;
        }

        return self::defaultLocale();
    }

    /**
     * Resolve from cookie → session/profile langauge → Staff DB profile → default.
     */
    public static function resolveFromRequest(): string
    {
        $cookie = self::readRawCookie(self::cookieName());
        if ($cookie !== '' && self::isSupported($cookie)) {
            return self::normalize($cookie);
        }

        $fromUser = (string) data_get(session('user'), 'langauge', data_get(session('user'), 'language', ''));
        if ($fromUser !== '' && self::isSupported($fromUser)) {
            return self::normalize($fromUser);
        }

        $fromDb = self::profileLocaleFromStaffDb();
        if ($fromDb !== null) {
            return $fromDb;
        }

        return self::defaultLocale();
    }

    public static function apply(string $locale, bool $persistProfile = false): string
    {
        $locale = self::normalize($locale);
        App::setLocale($locale);
        if (session()->isStarted()) {
            session(['portal_locale' => $locale]);
            $user = session('user');
            if (is_array($user)) {
                $user['langauge'] = $locale;
                session(['user' => $user]);
            }
        }

        if ($persistProfile) {
            self::persistProfileLocale($locale);
        }

        return $locale;
    }

    /**
     * Read cookie without relying on Laravel decryption (shared plain cookie).
     */
    private static function readRawCookie(string $name): string
    {
        $fromRequest = (string) request()->cookie($name, '');
        if ($fromRequest !== '') {
            return strtolower(trim($fromRequest));
        }

        $raw = (string) ($_COOKIE[$name] ?? '');

        return strtolower(trim($raw));
    }

    private static function profileLocaleFromStaffDb(): ?string
    {
        if (! self::staffDbConfigured()) {
            return null;
        }

        $staffId = (int) data_get(session('user'), 'staff_id', data_get(session('user'), 'auth_staff_id', 0));
        $userId = (int) data_get(session('user'), 'portal_user_id', 0);
        if ($staffId < 1 && $userId < 1) {
            // APM sometimes stores portal user_id under user_id; only use when staff_id missing.
            $maybeUserId = (int) data_get(session('user'), 'user_id', data_get(session('user'), 'id', 0));
            if ($maybeUserId > 0) {
                $userId = $maybeUserId;
            }
        }
        if ($staffId < 1 && $userId < 1) {
            return null;
        }

        try {
            if (! Schema::connection('staff_app')->hasTable('user')) {
                return null;
            }

            $query = DB::connection('staff_app')->table('user')->where('status', 1);
            if ($staffId > 0) {
                $query->where('auth_staff_id', $staffId);
            } else {
                $query->where('user_id', $userId);
            }

            $lang = strtolower(trim((string) $query->value('langauge')));
            if ($lang !== '' && self::isSupported($lang)) {
                return self::normalize($lang);
            }
        } catch (\Throwable $e) {
            Log::debug('PortalLocale: staff user.langauge unreadable: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Persist locale to Staff Portal profile (user.langauge) so modules share the default.
     */
    private static function persistProfileLocale(string $locale): void
    {
        if (! self::staffDbConfigured()) {
            return;
        }

        $staffId = (int) data_get(session('user'), 'staff_id', data_get(session('user'), 'auth_staff_id', 0));
        $userId = (int) data_get(session('user'), 'portal_user_id', 0);
        if ($staffId < 1 && $userId < 1) {
            $maybeUserId = (int) data_get(session('user'), 'user_id', data_get(session('user'), 'id', 0));
            // Prefer staff_id matching; if session user_id equals staff_id, use auth_staff_id path.
            if ($maybeUserId > 0 && $staffId < 1) {
                $staffId = $maybeUserId;
            }
        }
        if ($staffId < 1 && $userId < 1) {
            return;
        }

        try {
            if (! Schema::connection('staff_app')->hasTable('user')) {
                return;
            }

            $updated = 0;
            if ($staffId > 0) {
                $updated = DB::connection('staff_app')->table('user')
                    ->where('auth_staff_id', $staffId)
                    ->update(['langauge' => $locale]);
            }
            if ($updated < 1 && $userId > 0) {
                DB::connection('staff_app')->table('user')
                    ->where('user_id', $userId)
                    ->update(['langauge' => $locale]);
            }

            // Keep local APM mirror in sync when present.
            if (Schema::hasTable('apm_api_users') && $staffId > 0) {
                DB::table('apm_api_users')
                    ->where('auth_staff_id', $staffId)
                    ->update(['langauge' => $locale]);
            }
        } catch (\Throwable $e) {
            Log::debug('PortalLocale: failed to persist staff user.langauge: '.$e->getMessage());
        }
    }

    /**
     * Merged map for a UI group (english → defaults → Staff Portal DB overrides).
     *
     * @return array<string, string>
     */
    public static function group(string $group, ?string $locale = null): array
    {
        $locale = self::normalize($locale ?? App::getLocale());
        $english = (array) data_get(self::config(), "english.{$group}", []);
        $defaults = $locale !== 'en'
            ? (array) data_get(self::config(), "default_translations.{$locale}.{$group}", [])
            : [];
        $fromDb = self::translationsFromStaffDb($locale, $group);

        $out = [];
        foreach ($english as $key => $englishValue) {
            $key = (string) $key;
            $fromSaved = $fromDb[$key] ?? null;
            if (is_string($fromSaved) && $fromSaved !== '') {
                $out[$key] = $fromSaved;
                continue;
            }
            $fromDefault = $defaults[$key] ?? null;
            $out[$key] = is_string($fromDefault) && $fromDefault !== ''
                ? $fromDefault
                : (string) $englishValue;
        }

        return $out;
    }

    /**
     * Translate dotted key e.g. chrome.cbp_modules or pending_approvals.title.
     *
     * @param  array<string, scalar|null>  $replace
     */
    public static function t(string $key, ?string $fallback = null, ?string $locale = null, array $replace = []): string
    {
        $locale = self::normalize($locale ?? App::getLocale());
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return self::replacePlaceholders($fallback ?? $key, $replace);
        }
        [$group, $name] = $parts;
        $map = self::group($group, $locale);
        $value = $map[$name] ?? null;
        if (! is_string($value) || $value === '') {
            $value = $fallback ?? $name;
        }

        return self::replacePlaceholders($value, $replace);
    }

    /**
     * Catalog payload compatible with Staff Portal / Risk Register SPA.
     *
     * @return array{locale: string, direction: string, is_rtl: bool, languages: list<array<string, mixed>>, translations: array<string, array<string, string>>}
     */
    public static function catalog(?string $locale = null): array
    {
        $locale = self::normalize($locale ?? self::resolveFromRequest());
        $languages = array_values(self::languages());
        $translations = [];
        foreach (array_keys((array) (self::config()['english'] ?? [])) as $group) {
            $translations[(string) $group] = self::group((string) $group, $locale);
        }

        return [
            'locale' => $locale,
            'direction' => self::isRtl($locale) ? 'rtl' : 'ltr',
            'is_rtl' => self::isRtl($locale),
            'languages' => $languages,
            'translations' => $translations,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $replace
     */
    private static function replacePlaceholders(string $value, array $replace): string
    {
        foreach ($replace as $k => $v) {
            $value = str_replace(':'.$k, (string) $v, $value);
        }

        return $value;
    }

    private static function staffDbConfigured(): bool
    {
        $dbName = config('database.connections.staff_app.database');

        return is_string($dbName) && $dbName !== '';
    }

    /**
     * @return array<string, array{code: string, name: string, flag: string, google_code: string, sort_order: int, is_rtl: bool}>
     */
    private static function languagesFromStaffDb(): array
    {
        if (self::$staffDbLanguages !== null) {
            return self::$staffDbLanguages;
        }

        self::$staffDbLanguages = [];
        if (! self::staffDbConfigured()) {
            return self::$staffDbLanguages;
        }

        try {
            $conn = DB::connection('staff_app');
            if (! Schema::connection('staff_app')->hasTable('portal_languages')) {
                return self::$staffDbLanguages;
            }

            $rows = $conn->table('portal_languages')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['locale_code', 'name', 'google_translate_code', 'flag_emoji', 'sort_order']);

            if ($rows->isEmpty()) {
                return self::$staffDbLanguages;
            }

            $out = [];
            foreach ($rows as $row) {
                $code = strtolower((string) $row->locale_code);
                if ($code === '') {
                    continue;
                }
                $out[$code] = [
                    'code' => $code,
                    'name' => (string) $row->name,
                    'flag' => (string) ($row->flag_emoji ?? ''),
                    'google_code' => (string) ($row->google_translate_code ?: $code),
                    'sort_order' => (int) ($row->sort_order ?? 100),
                    'is_rtl' => self::isRtl($code),
                ];
            }
            self::$staffDbLanguages = $out;
        } catch (\Throwable $e) {
            Log::debug('PortalLocale: staff portal_languages unreadable: '.$e->getMessage());
            self::$staffDbLanguages = [];
        }

        return self::$staffDbLanguages;
    }

    /**
     * @return array<string, string>
     */
    private static function translationsFromStaffDb(string $locale, string $group): array
    {
        $all = self::loadStaffDbTranslations();

        return $all[$locale][$group] ?? [];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private static function loadStaffDbTranslations(): array
    {
        if (self::$staffDbTranslations !== null) {
            return self::$staffDbTranslations;
        }

        self::$staffDbTranslations = [];
        if (! self::staffDbConfigured()) {
            return self::$staffDbTranslations;
        }

        try {
            if (! Schema::connection('staff_app')->hasTable('portal_ui_translations')) {
                return self::$staffDbTranslations;
            }

            $groups = array_keys((array) (self::config()['english'] ?? []));
            if ($groups === []) {
                return self::$staffDbTranslations;
            }

            $rows = DB::connection('staff_app')
                ->table('portal_ui_translations')
                ->whereIn('group_key', $groups)
                ->get(['locale_code', 'group_key', 'item_key', 'value']);

            foreach ($rows as $row) {
                $loc = strtolower((string) $row->locale_code);
                $g = (string) $row->group_key;
                $k = (string) $row->item_key;
                $v = (string) $row->value;
                if ($loc === '' || $g === '' || $k === '' || $v === '') {
                    continue;
                }
                self::$staffDbTranslations[$loc][$g][$k] = $v;
            }
        } catch (\Throwable $e) {
            Log::debug('PortalLocale: staff portal_ui_translations unreadable: '.$e->getMessage());
            self::$staffDbTranslations = [];
        }

        return self::$staffDbTranslations;
    }
}
