<?php

namespace Modules\Settings\Services;

use Illuminate\Validation\ValidationException;
use Modules\Settings\Ai\PortalAiCompatibleClient;
use Modules\Settings\Support\PortalLocalesConfig;

class PortalAiTranslationService
{
    public function __construct(
        private PortalAiCompatibleClient $client,
        private PortalAiProviderService $providers,
        private PortalUiTranslationService $translations,
    ) {}

    /**
     * @return array<string, string>
     */
    public function suggestGroup(string $locale, string $group): array
    {
        $provider = $this->providers->defaultProvider();
        if ($provider === null || $this->providers->decryptKey($provider) === '') {
            throw ValidationException::withMessages([
                'ai' => 'No default AI provider with an API key is configured. Add a key under Settings → AI providers.',
            ]);
        }

        $english = $this->translations->englishGroup($group);
        if ($english === []) {
            throw ValidationException::withMessages(['group' => 'Unknown translation group.']);
        }

        $languageName = $this->languageName($locale);
        $payload = json_encode($english, JSON_UNESCAPED_UNICODE);
        $messages = [
            [
                'role' => 'system',
                'content' => 'You translate Staff Portal UI strings. Reply with a JSON object only. '
                    .'Keys must match the input keys. Values are short UI labels in the target language. '
                    .'Keep {placeholders} such as {year} and {locale} unchanged. Do not translate brand names Africa CDC, CBP, PPA, SAPNO, AU.',
            ],
            [
                'role' => 'user',
                'content' => "Target locale: {$locale} ({$languageName}). Group: {$group}.\nEnglish strings:\n{$payload}",
            ],
        ];

        $decoded = $this->client->chatJson($messages, 2500, $provider);
        if ($decoded === null) {
            throw ValidationException::withMessages([
                'ai' => 'The AI provider did not return usable translations. Check the connection test.',
            ]);
        }

        $out = [];
        foreach ($english as $key => $englishValue) {
            $suggested = $decoded[$key] ?? null;
            $out[$key] = is_string($suggested) && trim($suggested) !== ''
                ? trim($suggested)
                : (string) $englishValue;
        }

        return $out;
    }

    private function languageName(string $locale): string
    {
        $languages = PortalLocalesConfig::get('languages', []);
        if (is_array($languages) && isset($languages[$locale]['name'])) {
            return (string) $languages[$locale]['name'];
        }

        return $locale;
    }
}
