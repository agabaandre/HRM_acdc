<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * AU locale catalog for Helpdesk chrome (shared cookie with Staff Portal / Risk Register / APM).
 */
final class PortalLocale
{
    public static function config(): array
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config('supported_locales', []);

        return is_array($cfg) ? $cfg : [];
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
     * Resolve from cookie → session user.langauge → default.
     */
    public static function resolveFromRequest(): string
    {
        $cookie = (string) request()->cookie(self::cookieName(), '');
        if ($cookie !== '' && self::isSupported($cookie)) {
            return self::normalize($cookie);
        }

        $fromUser = (string) data_get(session('user'), 'langauge', data_get(session('user'), 'language', ''));
        if ($fromUser !== '' && self::isSupported($fromUser)) {
            return self::normalize($fromUser);
        }

        return self::defaultLocale();
    }

    public static function apply(string $locale): string
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

        return $locale;
    }

    /**
     * Translate dotted key e.g. chrome.cbp_modules.
     */
    public static function t(string $key, ?string $fallback = null, ?string $locale = null): string
    {
        $locale = self::normalize($locale ?? App::getLocale());
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return $fallback ?? $key;
        }
        [$group, $name] = $parts;

        if ($locale !== 'en') {
            $translated = data_get(self::config(), "default_translations.{$locale}.{$group}.{$name}");
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        $english = data_get(self::config(), "english.{$group}.{$name}");
        if (is_string($english) && $english !== '') {
            return $english;
        }

        return $fallback ?? $name;
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
        $translations = [
            'chrome' => (array) (self::config()['english']['chrome'] ?? []),
        ];
        if ($locale !== 'en') {
            $override = (array) data_get(self::config(), "default_translations.{$locale}.chrome", []);
            $translations['chrome'] = array_merge($translations['chrome'], $override);
        }

        return [
            'locale' => $locale,
            'direction' => self::isRtl($locale) ? 'rtl' : 'ltr',
            'is_rtl' => self::isRtl($locale),
            'languages' => $languages,
            'translations' => $translations,
        ];
    }
}
