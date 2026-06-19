<?php

namespace App\Ai;

use App\Models\HelpdeskSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI-compatible chat client using Helpdesk admin AI settings.
 * Mirrors the Laravel AI SDK agent provider pattern; swap for laravel/ai when PHP ≥ 8.3.
 */
class OpenAiCompatibleClient
{
    public function isConfigured(): bool
    {
        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_ACTIVE, '0') !== '1') {
            return false;
        }

        return $this->apiKey() !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, int $maxTokens = 800, float $temperature = 0.35): ?string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return null;
        }

        $endpoint = rtrim((string) HelpdeskSetting::getValue(
            HelpdeskSetting::KEY_AI_API_ENDPOINT,
            'https://api.openai.com/v1'
        ), '/');
        $model = (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_MODEL_NAME, 'gpt-4o-mini');

        try {
            $response = Http::timeout(45)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint.'/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $choice = $response->json('choices.0.message.content');

            return is_string($choice) ? trim($choice) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function apiKey(): string
    {
        $enc = HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_KEY);
        if ($enc === null || $enc === '') {
            return '';
        }

        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return '';
        }
    }
}
