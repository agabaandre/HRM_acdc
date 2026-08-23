<?php

namespace Modules\Settings\Ai;

use Illuminate\Support\Facades\Http;
use Modules\Settings\Models\PortalAiProvider;
use Modules\Settings\Services\PortalAiProviderService;

/**
 * OpenAI-compatible chat client (Helpdesk pattern).
 */
class PortalAiCompatibleClient
{
    public function __construct(
        private PortalAiProviderService $providers,
    ) {}

    /**
     * Newer OpenAI models (GPT-5 / o-series) reject `max_tokens` in favor of `max_completion_tokens`.
     */
    public static function usesMaxCompletionTokens(string $model): bool
    {
        $m = strtolower(trim($model));

        return str_starts_with($m, 'gpt-5')
            || str_starts_with($m, 'o1')
            || str_starts_with($m, 'o3')
            || str_starts_with($m, 'o4');
    }

    /**
     * @return array{max_completion_tokens: int}|array{max_tokens: int}
     */
    public static function completionLimitFields(string $model, int $limit): array
    {
        if (self::usesMaxCompletionTokens($model)) {
            return ['max_completion_tokens' => $limit];
        }

        return ['max_tokens' => $limit];
    }

    public static function supportsCustomTemperature(string $model): bool
    {
        return ! self::usesMaxCompletionTokens($model);
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     provider: string,
     *     endpoint: string,
     *     model: string,
     *     key_present: bool,
     *     latency_ms: int|null,
     *     http_status: int|null,
     *     reply_preview: string|null
     * }
     */
    public function testConnection(
        ?PortalAiProvider $provider = null,
        ?string $apiKeyOverride = null,
        ?string $endpointOverride = null,
        ?string $modelOverride = null,
        ?string $driverOverride = null,
    ): array {
        $provider ??= $this->providers->defaultProvider();
        $driver = $driverOverride !== null && trim($driverOverride) !== ''
            ? trim($driverOverride)
            : (string) ($provider?->driver ?? 'openai');
        $endpoint = rtrim(
            $endpointOverride !== null && trim($endpointOverride) !== ''
                ? trim($endpointOverride)
                : (string) ($provider?->api_endpoint ?? ''),
            '/',
        );
        $model = $modelOverride !== null && trim($modelOverride) !== ''
            ? trim($modelOverride)
            : (string) ($provider?->model ?? '');

        $preset = $this->providers->driverDefinition($driver);
        if ($endpoint === '' && is_array($preset)) {
            $endpoint = rtrim((string) ($preset['api_endpoint'] ?? ''), '/');
        }
        if ($model === '' && is_array($preset)) {
            $model = (string) ($preset['model'] ?? '');
        }

        $apiKey = $apiKeyOverride !== null && trim($apiKeyOverride) !== ''
            ? trim($apiKeyOverride)
            : $this->providers->decryptKey($provider);

        $base = [
            'ok' => false,
            'message' => '',
            'provider' => $driver !== '' ? $driver : 'openai',
            'endpoint' => $endpoint,
            'model' => $model,
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
            $payload = array_merge([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Reply with exactly: ok',
                    ],
                ],
            ], self::completionLimitFields($model, 16));

            if (self::supportsCustomTemperature($model)) {
                $payload['temperature'] = 0;
            }

            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint.'/chat/completions', $payload);

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
            $base['message'] = 'AI connection succeeded. Provider is reachable.';

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
    public function chat(
        array $messages,
        int $maxTokens = 800,
        float $temperature = 0.35,
        bool $jsonObject = false,
        ?PortalAiProvider $provider = null,
    ): ?string {
        $provider ??= $this->providers->defaultProvider();
        $apiKey = $this->providers->decryptKey($provider);
        if ($apiKey === '' || $provider === null) {
            return null;
        }

        $endpoint = rtrim((string) $provider->api_endpoint, '/');
        $model = (string) $provider->model;
        $preset = $this->providers->driverDefinition((string) $provider->driver);
        if ($endpoint === '' && is_array($preset)) {
            $endpoint = rtrim((string) ($preset['api_endpoint'] ?? ''), '/');
        }
        if ($model === '' && is_array($preset)) {
            $model = (string) ($preset['model'] ?? '');
        }
        if ($endpoint === '' || $model === '') {
            return null;
        }

        try {
            $payload = array_merge([
                'model' => $model,
                'messages' => $messages,
            ], self::completionLimitFields($model, $maxTokens));

            if ($jsonObject) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            if (self::supportsCustomTemperature($model)) {
                $payload['temperature'] = $temperature;
            }

            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint.'/chat/completions', $payload);

            if (! $response->successful()) {
                return null;
            }

            $choice = $response->json('choices.0.message.content');

            return is_string($choice) ? trim($choice) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>|null
     */
    public function chatJson(array $messages, int $maxTokens = 2500, ?PortalAiProvider $provider = null): ?array
    {
        $raw = $this->chat($messages, $maxTokens, 0.2, true, $provider)
            ?? $this->chat($messages, $maxTokens, 0.2, false, $provider);
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $raw, $match) === 1) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
