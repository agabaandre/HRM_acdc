<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Settings\Models\PortalLanguage;
use Modules\Settings\Models\PortalUiTranslation;
use RuntimeException;

use Modules\Settings\Support\PortalLocalesConfig;

class PortalUiTranslationService
{
    /**
     * @return array<string, string>
     */
    public function groups(): array
    {
        $groups = PortalLocalesConfig::get('ui_groups', []);

        return is_array($groups) ? $groups : [];
    }

    /**
     * @return array<string, string>
     */
    public function englishGroup(string $group): array
    {
        $english = PortalLocalesConfig::get('english.'.$group, []);

        return is_array($english) ? $english : [];
    }

    /**
     * @return array<string, string>
     */
    public function loadMerged(string $locale, string $group): array
    {
        $english = $this->englishGroup($group);
        if ($english === []) {
            return [];
        }

        $defaults = PortalLocalesConfig::get('default_translations.'.$locale.'.'.$group, []);
        $defaults = is_array($defaults) ? $defaults : [];
        $saved = $this->savedMap($locale, $group);

        $out = [];
        foreach ($english as $key => $englishValue) {
            $fromDb = $saved[$key] ?? null;
            if (is_string($fromDb) && $fromDb !== '') {
                $out[$key] = $fromDb;
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
     * Nested translations for the live UI.
     *
     * @return array<string, array<string, string>>
     */
    public function translationsForLocale(string $locale): array
    {
        $out = [];
        foreach (array_keys($this->groups()) as $group) {
            $out[$group] = $this->loadMerged($locale, $group);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $submitted
     */
    public function saveGroup(string $locale, string $group, array $submitted): void
    {
        if (! in_array($locale, PortalLanguage::allLocaleCodes(), true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
        if (! array_key_exists($group, $this->groups())) {
            throw new InvalidArgumentException('Unsupported translation group.');
        }

        $english = $this->englishGroup($group);
        if ($english === []) {
            throw new RuntimeException('English source missing for group: '.$group);
        }

        if (! Schema::hasTable('portal_ui_translations')) {
            throw new RuntimeException('portal_ui_translations table is missing. Run migrations.');
        }

        foreach (array_keys($english) as $key) {
            $raw = $submitted[$key] ?? '';
            $value = is_string($raw) ? $raw : (string) $raw;

            PortalUiTranslation::query()->updateOrCreate(
                [
                    'locale_code' => $locale,
                    'group_key' => $group,
                    'item_key' => $key,
                ],
                ['value' => $value],
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function savedMap(string $locale, string $group): array
    {
        if (! Schema::hasTable('portal_ui_translations')) {
            return [];
        }

        return PortalUiTranslation::query()
            ->where('locale_code', $locale)
            ->where('group_key', $group)
            ->pluck('value', 'item_key')
            ->all();
    }
}
