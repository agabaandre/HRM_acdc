<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Optional AI pick among eligible agents; returns null to use deterministic routing.
 */
class AiAgentPickerService
{
    public function pickUserId(HelpdeskTicket $ticket, array $eligibleUserIds, ?string $requesterDutyStation): ?int
    {
        if ($eligibleUserIds === []) {
            return null;
        }

        if (! HelpdeskSetting::aiAgentAssignmentEnabled()) {
            return null;
        }

        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_ACTIVE, '0') !== '1') {
            return null;
        }

        $enc = HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_KEY);
        if ($enc === null || $enc === '') {
            return null;
        }

        try {
            $apiKey = Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }

        if ($apiKey === '') {
            return null;
        }

        $endpoint = rtrim((string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_ENDPOINT, 'https://api.openai.com/v1'), '/');
        $model = (string) HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_MODEL_NAME, 'gpt-4o-mini');

        $agents = [];
        $profiles = HelpdeskProfile::query()->whereIn('user_id', $eligibleUserIds)->get()->keyBy('user_id');
        foreach ($eligibleUserIds as $uid) {
            $u = User::query()->find($uid);
            $p = $profiles->get($uid);
            $load = HelpdeskTicket::query()
                ->where('assigned_user_id', $uid)
                ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                ->count();
            $agents[] = [
                'user_id' => $uid,
                'name' => $u?->name ?? 'Agent',
                'duty_station' => $p?->duty_station ? trim((string) $p->duty_station) : '',
                'division_id' => $p?->division_id,
                'open_ticket_load' => $load,
            ];
        }

        $payload = [
            'ticket_category_id' => $ticket->category_id,
            'requester_division_id' => $ticket->division_id,
            'requester_directorate_id' => $ticket->directorate_id,
            'requester_duty_station' => $requesterDutyStation ?? '',
            'agents' => $agents,
        ];

        $system = 'You assign IT helpdesk tickets to exactly one agent user_id from the given JSON agents list. '
            .'Prefer agents with lower open_ticket_load when skills match; consider duty_station and division_id alignment with the requester. '
            .'Reply with a single JSON object only, shape {"user_id":<integer>} using one of the listed user_id values, or {"user_id":null} if unsure.';

        try {
            $response = Http::timeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint.'/chat/completions', [
                    'model' => $model,
                    'max_tokens' => 60,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)],
                    ],
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/\{[^}]*"user_id"\s*:\s*(\d+)/', $text, $m)) {
            $picked = (int) $m[1];
            if (in_array($picked, $eligibleUserIds, true)) {
                return $picked;
            }
        }

        return null;
    }
}
