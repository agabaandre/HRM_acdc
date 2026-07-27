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
     * Probe the configured (or override) OpenAI-compatible endpoint with a tiny chat call.
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     provider: string,
     *     endpoint: string,
     *     model: string,
     *     ai_active: bool,
     *     key_present: bool,
     *     latency_ms: int|null,
     *     http_status: int|null,
     *     reply_preview: string|null
     * }
     */
    public function testConnection(
        ?string $apiKeyOverride = null,
        ?string $endpointOverride = null,
        ?string $modelOverride = null,
    ): array {
        $provider = (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_PROVIDER, 'openai');
        $endpoint = rtrim(
            $endpointOverride !== null && trim($endpointOverride) !== ''
                ? trim($endpointOverride)
                : (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_ENDPOINT, 'https://api.openai.com/v1'),
            '/'
        );
        $model = $modelOverride !== null && trim($modelOverride) !== ''
            ? trim($modelOverride)
            : (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_MODEL_NAME, 'gpt-4o-mini');
        $aiActive = HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_ACTIVE, '0') === '1';

        $apiKey = $apiKeyOverride !== null && trim($apiKeyOverride) !== ''
            ? trim($apiKeyOverride)
            : $this->apiKey();

        $base = [
            'ok' => false,
            'message' => '',
            'provider' => $provider !== '' ? $provider : 'openai',
            'endpoint' => $endpoint,
            'model' => $model,
            'ai_active' => $aiActive,
            'key_present' => $apiKey !== '',
            'latency_ms' => null,
            'http_status' => null,
            'reply_preview' => null,
        ];

        if ($apiKey === '') {
            $base['message'] = 'No API key is configured. Save an API key first, or enter one in the form and test again.';

            return $base;
        }

        if ($endpoint === '') {
            $base['message'] = 'API base URL is empty.';

            return $base;
        }

        if ($model === '') {
            $base['message'] = 'Model name is empty.';

            return $base;
        }

        $started = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint.'/chat/completions', [
                    'model' => $model,
                    'max_tokens' => 16,
                    'temperature' => 0,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Reply with exactly: ok',
                        ],
                    ],
                ]);

            $base['latency_ms'] = (int) round((microtime(true) - $started) * 1000);
            $base['http_status'] = $response->status();

            if (! $response->successful()) {
                $err = $response->json('error.message');
                $detail = is_string($err) && $err !== '' ? $err : $response->body();
                $detail = mb_substr(trim((string) $detail), 0, 400);
                $base['message'] = 'Provider returned HTTP '.$response->status()
                    .($detail !== '' ? ': '.$detail : '');

                return $base;
            }

            $choice = $response->json('choices.0.message.content');
            $preview = is_string($choice) ? trim($choice) : '';
            $base['ok'] = true;
            $base['reply_preview'] = $preview !== '' ? mb_substr($preview, 0, 120) : null;
            $base['message'] = $aiActive
                ? 'AI connection succeeded. Provider is reachable and AI is active.'
                : 'AI connection succeeded. Provider is reachable — turn on “AI active” to use it in Helpdesk.';

            return $base;
        } catch (\Throwable $e) {
            $base['latency_ms'] = (int) round((microtime(true) - $started) * 1000);
            $base['message'] = 'Could not reach the AI endpoint: '.$e->getMessage();

            return $base;
        }
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
