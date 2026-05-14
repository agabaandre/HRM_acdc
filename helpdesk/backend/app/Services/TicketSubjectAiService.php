<?php

namespace App\Services;

use App\Models\HelpdeskSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Optional OpenAI-style hint for auto-generated ticket subjects (URS §10).
 * Falls back to a short heuristic when AI is off or the request fails.
 */
class TicketSubjectAiService
{
    public function hint(string $plainDescription): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($plainDescription)));
        if ($plain === '') {
            return '';
        }

        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_ACTIVE, '0') === '1') {
            $enc = HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_KEY);
            if ($enc !== null && $enc !== '') {
                try {
                    $apiKey = Crypt::decryptString($enc);
                } catch (\Throwable) {
                    $apiKey = '';
                }
                if ($apiKey !== '') {
                    $endpoint = rtrim((string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_ENDPOINT, 'https://api.openai.com/v1'), '/');
                    $model = (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_MODEL_NAME, 'gpt-4o-mini');
                    $fragment = $this->callChatCompletion($endpoint, $apiKey, $model, Str::limit($plain, 500, ''));
                    if ($fragment !== '') {
                        return Str::limit(trim($fragment), 48, '');
                    }
                }
            }
        }

        return $this->heuristicHint($plain);
    }

    private function callChatCompletion(string $baseV1, string $apiKey, string $model, string $userText): string
    {
        $url = $baseV1.'/chat/completions';
        try {
            $response = Http::timeout(12)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($url, [
                    'model' => $model,
                    'max_tokens' => 40,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Output only a very short phrase (max 8 words) summarizing the core issue for an IT ticket subject. No quotes, no colon, no name.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $userText,
                        ],
                    ],
                ]);
            if (! $response->successful()) {
                return '';
            }
            $choice = $response->json('choices.0.message.content');

            return is_string($choice) ? trim($choice) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function heuristicHint(string $plain): string
    {
        $lower = strtolower($plain);
        $map = [
            'password' => 'password or access',
            'login' => 'sign-in issue',
            'email' => 'email issue',
            'vpn' => 'remote access',
            'printer' => 'printing',
            'network' => 'network connectivity',
            'slow' => 'performance',
            'error' => 'application error',
            'install' => 'software install',
        ];
        foreach ($map as $needle => $hint) {
            if (str_contains($lower, $needle)) {
                return $hint;
            }
        }

        return Str::limit($plain, 32, '');
    }
}
