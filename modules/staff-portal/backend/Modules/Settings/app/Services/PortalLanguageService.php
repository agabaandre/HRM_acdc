<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\PortalUser;
use Modules\Settings\Models\PortalLanguage;
use Modules\Settings\Support\PortalLocalesConfig;

class PortalLanguageService
{
    public function __construct(
        protected PortalUiTranslationService $translations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function localesConfig(): array
    {
        $cfg = PortalLocalesConfig::all();

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @return list<string>
     */
    public function rtlLocales(): array
    {
        $configured = $this->localesConfig()['rtl_locales'] ?? ['ar'];

        return array_values(array_unique(array_filter(
            is_array($configured) ? $configured : ['ar'],
            static fn ($code) => is_string($code) && $code !== '',
        )));
    }

    public function isRtl(?string $locale): bool
    {
        if ($locale === null || $locale === '') {
            return false;
        }

        return in_array(strtolower($locale), array_map('strtolower', $this->rtlLocales()), true);
    }

    public function direction(?string $locale): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }

    public function seedAuLanguages(): void
    {
        if (! Schema::hasTable('portal_languages')) {
            return;
        }

        $now = now();
        $languages = $this->localesConfig()['languages'] ?? [];
        if (! is_array($languages)) {
            return;
        }

        foreach ($languages as $code => $row) {
            if (! is_string($code) || $code === '' || ! is_array($row)) {
                continue;
            }

            $exists = PortalLanguage::query()->where('locale_code', $code)->exists();
            if ($exists) {
                continue;
            }

            PortalLanguage::query()->create([
                'locale_code' => $code,
                'name' => (string) ($row['name'] ?? $code),
                'google_translate_code' => (string) ($row['google_code'] ?? $code),
                'flag_emoji' => (string) ($row['flag'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 100),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return Collection<int, PortalLanguage>
     */
    public function listForAdmin(): Collection
    {
        $this->seedAuLanguages();

        return PortalLanguage::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PortalLanguage
    {
        $this->seedAuLanguages();

        $language = PortalLanguage::query()->create([
            'locale_code' => strtolower(trim((string) $data['locale_code'])),
            'name' => (string) $data['name'],
            'google_translate_code' => $this->nullableString($data['google_translate_code'] ?? null),
            'flag_emoji' => $this->nullableString($data['flag_emoji'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        PortalLanguage::flushCache();

        return $language;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PortalLanguage $language, array $data): PortalLanguage
    {
        $language->update([
            'name' => (string) $data['name'],
            'google_translate_code' => $this->nullableString($data['google_translate_code'] ?? null),
            'flag_emoji' => $this->nullableString($data['flag_emoji'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? $language->sort_order),
            'is_active' => (bool) ($data['is_active'] ?? $language->is_active),
        ]);
        PortalLanguage::flushCache();

        return $language->refresh();
    }

    public function delete(PortalLanguage $language): void
    {
        if (strtolower((string) $language->locale_code) === 'en') {
            throw ValidationException::withMessages([
                'locale_code' => ['English (en) cannot be removed.'],
            ]);
        }

        if (Schema::hasTable('user')) {
            $inUse = DB::table('user')->where('langauge', $language->locale_code)->exists();
            if ($inUse) {
                throw ValidationException::withMessages([
                    'locale_code' => ['Cannot delete: users still have this language. Deactivate it instead.'],
                ]);
            }
        }

        $code = (string) $language->locale_code;
        $language->delete();
        if (Schema::hasTable('portal_ui_translations')) {
            DB::table('portal_ui_translations')->where('locale_code', $code)->delete();
        }
        PortalLanguage::flushCache();
    }

    public function resolveActiveLocale(?string $userLocale, ?string $cookieLocale): string
    {
        $map = PortalLanguage::selectorMap();
        $codes = array_keys($map);
        $default = in_array('en', $codes, true) ? 'en' : ($codes[0] ?? 'en');

        if (is_string($userLocale) && $userLocale !== '' && in_array($userLocale, $codes, true)) {
            return $userLocale;
        }
        if (is_string($cookieLocale) && $cookieLocale !== '' && in_array($cookieLocale, $codes, true)) {
            return $cookieLocale;
        }

        return $default;
    }

    /**
     * @return list<array{code: string, name: string, flag: string}>
     */
    public function profileOptions(): array
    {
        $out = [];
        foreach (PortalLanguage::selectorMap() as $code => $row) {
            $out[] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
                'flag' => (string) ($row['flag'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(?string $userLocale, ?string $cookieLocale): array
    {
        $this->seedAuLanguages();
        $locale = $this->resolveActiveLocale($userLocale, $cookieLocale);
        $languages = [];
        foreach (PortalLanguage::selectorMap() as $code => $row) {
            $languages[] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
                'flag' => (string) ($row['flag'] ?? ''),
                'google_code' => (string) ($row['google_code'] ?? $code),
                'is_rtl' => $this->isRtl($code),
            ];
        }

        return [
            'locale' => $locale,
            'direction' => $this->direction($locale),
            'is_rtl' => $this->isRtl($locale),
            'languages' => $languages,
            'groups' => $this->translations->groups(),
            'translations' => $this->translations->translationsForLocale($locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applyLocale(PortalUser $user, string $locale): array
    {
        $this->seedAuLanguages();
        $supported = PortalLanguage::activeLocaleCodes();
        if (! in_array($locale, $supported, true)) {
            throw ValidationException::withMessages([
                'locale' => ['Unsupported locale.'],
            ]);
        }

        if (Schema::hasTable('user')) {
            DB::table('user')->where('user_id', $user->user_id)->update(['langauge' => $locale]);
        }
        $user->langauge = $locale;

        $map = PortalLanguage::selectorMap();

        return [
            'locale' => $locale,
            'direction' => $this->direction($locale),
            'is_rtl' => $this->isRtl($locale),
            'google_code' => $map[$locale]['google_code'] ?? $locale,
            'translations' => $this->translations->translationsForLocale($locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(PortalLanguage $language): array
    {
        return [
            'id' => (int) $language->id,
            'locale_code' => (string) $language->locale_code,
            'name' => (string) $language->name,
            'google_translate_code' => $language->google_translate_code,
            'flag_emoji' => $language->flag_emoji,
            'sort_order' => (int) $language->sort_order,
            'is_active' => (bool) $language->is_active,
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
