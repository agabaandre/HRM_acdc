<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class HelpdeskSettingsController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function show(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $keys = [
            HelpdeskSetting::KEY_AI_PROVIDER,
            HelpdeskSetting::KEY_AI_API_ENDPOINT,
            HelpdeskSetting::KEY_AI_MODEL_NAME,
            HelpdeskSetting::KEY_AI_ACTIVE,
            HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT,
            HelpdeskSetting::KEY_AI_FALLBACK_ORDER,
            HelpdeskSetting::KEY_BRANDING_PRIMARY,
            HelpdeskSetting::KEY_BRANDING_SECONDARY,
            HelpdeskSetting::KEY_DEFAULT_AGENT_DIVISION_IDS,
            HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION,
            HelpdeskSetting::KEY_WHATSAPP_ENABLED,
            HelpdeskSetting::KEY_WHATSAPP_PHONE_NUMBER_ID,
            HelpdeskSetting::KEY_WHATSAPP_VERIFY_TOKEN,
            HelpdeskSetting::KEY_TEAMS_ENABLED,
            HelpdeskSetting::KEY_TEAMS_APP_ID,
            HelpdeskSetting::KEY_TEAMS_TENANT_ID,
            HelpdeskSetting::KEY_TEAMS_MESSAGING_PATH,
        ];

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = HelpdeskSetting::getValue($key);
        }

        $data[HelpdeskSetting::KEY_AI_ACTIVE] = (($data[HelpdeskSetting::KEY_AI_ACTIVE] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT] = (($data[HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION] = (($data[HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_WHATSAPP_ENABLED] = (($data[HelpdeskSetting::KEY_WHATSAPP_ENABLED] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_TEAMS_ENABLED] = (($data[HelpdeskSetting::KEY_TEAMS_ENABLED] ?? '0') === '1');

        $rawKey = HelpdeskSetting::getValue(HelpdeskSetting::KEY_AI_API_KEY);
        $data[HelpdeskSetting::KEY_AI_API_KEY] = '';
        $data['ai_api_key_configured'] = $rawKey !== null && $rawKey !== '';

        $waTok = HelpdeskSetting::getValue(HelpdeskSetting::KEY_WHATSAPP_ACCESS_TOKEN);
        $data[HelpdeskSetting::KEY_WHATSAPP_ACCESS_TOKEN] = '';
        $data['whatsapp_access_token_configured'] = $waTok !== null && $waTok !== '';

        $waSec = HelpdeskSetting::getValue(HelpdeskSetting::KEY_WHATSAPP_APP_SECRET);
        $data[HelpdeskSetting::KEY_WHATSAPP_APP_SECRET] = '';
        $data['whatsapp_app_secret_configured'] = $waSec !== null && $waSec !== '';

        $teamsPwd = HelpdeskSetting::getValue(HelpdeskSetting::KEY_TEAMS_APP_PASSWORD);
        $data[HelpdeskSetting::KEY_TEAMS_APP_PASSWORD] = '';
        $data['teams_app_password_configured'] = $teamsPwd !== null && $teamsPwd !== '';

        $base = rtrim((string) config('app.url'), '/');
        $data['webhook_base_url'] = $base.'/api/v1/webhooks';
        $data['iso_json_log_in_stack'] = str_contains((string) env('LOG_STACK', ''), 'iso_json');

        return response()->json(['data' => $data]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'ai_provider' => ['nullable', 'string', Rule::in(['openai', 'gemini', 'custom'])],
            'ai_api_endpoint' => ['nullable', 'string', 'max:512'],
            'ai_model_name' => ['nullable', 'string', 'max:191'],
            'ai_active' => ['nullable', 'boolean'],
            'ai_agent_assignment_enabled' => ['nullable', 'boolean'],
            'ai_fallback_order' => ['nullable', 'string', 'max:512'],
            'branding_primary_hex' => ['nullable', 'string', 'max:32'],
            'branding_secondary_hex' => ['nullable', 'string', 'max:32'],
            'default_agent_division_ids' => ['nullable', 'string', 'max:128'],
            'require_resolution_confirmation' => ['nullable', 'boolean'],
            'ai_api_key' => ['nullable', 'string', 'max:8192'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:191'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:8192'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:8192'],
            'teams_enabled' => ['nullable', 'boolean'],
            'teams_app_id' => ['nullable', 'string', 'max:128'],
            'teams_tenant_id' => ['nullable', 'string', 'max:128'],
            'teams_messaging_path' => ['nullable', 'string', 'max:191'],
            'teams_app_password' => ['nullable', 'string', 'max:8192'],
        ]);

        $map = [
            'ai_provider' => HelpdeskSetting::KEY_AI_PROVIDER,
            'ai_api_endpoint' => HelpdeskSetting::KEY_AI_API_ENDPOINT,
            'ai_model_name' => HelpdeskSetting::KEY_AI_MODEL_NAME,
            'ai_fallback_order' => HelpdeskSetting::KEY_AI_FALLBACK_ORDER,
            'branding_primary_hex' => HelpdeskSetting::KEY_BRANDING_PRIMARY,
            'branding_secondary_hex' => HelpdeskSetting::KEY_BRANDING_SECONDARY,
            'default_agent_division_ids' => HelpdeskSetting::KEY_DEFAULT_AGENT_DIVISION_IDS,
            'whatsapp_phone_number_id' => HelpdeskSetting::KEY_WHATSAPP_PHONE_NUMBER_ID,
            'whatsapp_verify_token' => HelpdeskSetting::KEY_WHATSAPP_VERIFY_TOKEN,
            'teams_app_id' => HelpdeskSetting::KEY_TEAMS_APP_ID,
            'teams_tenant_id' => HelpdeskSetting::KEY_TEAMS_TENANT_ID,
            'teams_messaging_path' => HelpdeskSetting::KEY_TEAMS_MESSAGING_PATH,
        ];

        foreach ($map as $reqKey => $dbKey) {
            if (! array_key_exists($reqKey, $validated)) {
                continue;
            }
            $v = $validated[$reqKey];
            HelpdeskSetting::setValue($dbKey, $v === null ? null : (string) $v);
        }

        if (array_key_exists('ai_active', $validated) && $validated['ai_active'] !== null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_AI_ACTIVE, $validated['ai_active'] ? '1' : '0');
        }

        if (array_key_exists('ai_agent_assignment_enabled', $validated) && $validated['ai_agent_assignment_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT,
                $validated['ai_agent_assignment_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('require_resolution_confirmation', $validated) && $validated['require_resolution_confirmation'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION,
                $validated['require_resolution_confirmation'] ? '1' : '0'
            );
        }

        if (array_key_exists('whatsapp_enabled', $validated) && $validated['whatsapp_enabled'] !== null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_WHATSAPP_ENABLED, $validated['whatsapp_enabled'] ? '1' : '0');
        }

        if (array_key_exists('teams_enabled', $validated) && $validated['teams_enabled'] !== null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_TEAMS_ENABLED, $validated['teams_enabled'] ? '1' : '0');
        }

        if (array_key_exists('ai_api_key', $validated)) {
            $incoming = $validated['ai_api_key'];
            if ($incoming !== null && $incoming !== '') {
                HelpdeskSetting::setValue(
                    HelpdeskSetting::KEY_AI_API_KEY,
                    Crypt::encryptString($incoming)
                );
            }
        }

        foreach (['whatsapp_access_token' => HelpdeskSetting::KEY_WHATSAPP_ACCESS_TOKEN, 'whatsapp_app_secret' => HelpdeskSetting::KEY_WHATSAPP_APP_SECRET, 'teams_app_password' => HelpdeskSetting::KEY_TEAMS_APP_PASSWORD] as $req => $dbKey) {
            if (! array_key_exists($req, $validated)) {
                continue;
            }
            $incoming = $validated[$req];
            if ($incoming !== null && $incoming !== '') {
                HelpdeskSetting::setValue($dbKey, Crypt::encryptString($incoming));
            }
        }

        return $this->show($request);
    }
}
