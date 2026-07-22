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
            HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP,
            HelpdeskSetting::KEY_WHATSAPP_ENABLED,
            HelpdeskSetting::KEY_WHATSAPP_PHONE_NUMBER_ID,
            HelpdeskSetting::KEY_WHATSAPP_VERIFY_TOKEN,
            HelpdeskSetting::KEY_TEAMS_ENABLED,
            HelpdeskSetting::KEY_TEAMS_APP_ID,
            HelpdeskSetting::KEY_TEAMS_TENANT_ID,
            HelpdeskSetting::KEY_TEAMS_MESSAGING_PATH,
            HelpdeskSetting::KEY_FAQ_SOURCES_JSON,
            HelpdeskSetting::KEY_FAQ_INGEST_LAST_RESULT,
            HelpdeskSetting::KEY_SCREEN_AGENT_TICKETS_WEIGHT,
            HelpdeskSetting::KEY_SCREEN_AGENT_RESPONSE_WEIGHT,
            HelpdeskSetting::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE,
            HelpdeskSetting::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE,
            HelpdeskSetting::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS,
            HelpdeskSetting::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS,
            HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED,
            HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED,
            HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS,
            HelpdeskSetting::KEY_RESOLVED_AUTO_CLOSE_DAYS,
            HelpdeskSetting::KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED,
            HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED,
            HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS,
            HelpdeskSetting::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS,
            HelpdeskSetting::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS,
            HelpdeskSetting::KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM,
            HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED,
        ];

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = HelpdeskSetting::getValue($key);
        }

        $data[HelpdeskSetting::KEY_AI_ACTIVE] = (($data[HelpdeskSetting::KEY_AI_ACTIVE] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT] = (($data[HelpdeskSetting::KEY_AI_AGENT_ASSIGNMENT] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION] = (($data[HelpdeskSetting::KEY_REQUIRE_RESOLUTION_CONFIRMATION] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP] = (($data[HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_WHATSAPP_ENABLED] = (($data[HelpdeskSetting::KEY_WHATSAPP_ENABLED] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_TEAMS_ENABLED] = (($data[HelpdeskSetting::KEY_TEAMS_ENABLED] ?? '0') === '1');
        $data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED] = (($data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED] = (($data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS] = (int) ($data[HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS] ?? 12);
        $data[HelpdeskSetting::KEY_RESOLVED_AUTO_CLOSE_DAYS] = (int) ($data[HelpdeskSetting::KEY_RESOLVED_AUTO_CLOSE_DAYS] ?? 7);
        $data[HelpdeskSetting::KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED] = (($data[HelpdeskSetting::KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED] = (($data[HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED] ?? '1') === '1');
        $data[HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS] = HelpdeskSetting::licenseExpiryAlertIntervalDays();
        $data[HelpdeskSetting::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS] = $data[HelpdeskSetting::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS] ?? '';
        $data[HelpdeskSetting::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS] = $data[HelpdeskSetting::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS] ?? '';
        $data[HelpdeskSetting::KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM] = HelpdeskSetting::showIssueCategoryOnRequestForm();
        $data[HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED] = HelpdeskSetting::emailTicketIntakeEnabled();
        $data[HelpdeskSetting::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE] = HelpdeskSetting::screenDutyStationItemsPerPage();
        $data[HelpdeskSetting::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE] = HelpdeskSetting::screenCategoryItemsPerPage();
        $data[HelpdeskSetting::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS] = HelpdeskSetting::screenListSliderIntervalSeconds();
        $data[HelpdeskSetting::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS] = HelpdeskSetting::screenSupportGroupSliderIntervalSeconds();

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

        $faqSourcesRaw = HelpdeskSetting::getValue(HelpdeskSetting::KEY_FAQ_SOURCES_JSON);
        $data[HelpdeskSetting::KEY_FAQ_SOURCES_JSON] = $faqSourcesRaw ?? '';
        $lastIngestRaw = HelpdeskSetting::getValue(HelpdeskSetting::KEY_FAQ_INGEST_LAST_RESULT);
        $data[HelpdeskSetting::KEY_FAQ_INGEST_LAST_RESULT] = $lastIngestRaw ?? '';

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
            'requester_unsatisfied_follow_up_enabled' => ['nullable', 'boolean'],
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
            'faq_sources_json' => ['nullable', 'string', 'max:65535'],
            'screen_agent_leaderboard_tickets_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'screen_agent_leaderboard_response_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'screen_duty_station_items_per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'screen_category_items_per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'screen_list_slider_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:60'],
            'screen_support_group_slider_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:60'],
            'agent_monthly_report_enabled' => ['nullable', 'boolean'],
            'agent_monthly_report_email_enabled' => ['nullable', 'boolean'],
            'agent_monthly_report_retention_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'resolved_auto_close_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'agent_open_ticket_reminder_enabled' => ['nullable', 'boolean'],
            'license_expiry_alert_enabled' => ['nullable', 'boolean'],
            'license_expiry_alert_interval_days' => ['nullable', 'integer', Rule::in([1, 3, 7])],
            'software_request_notify_group_ids' => ['nullable', 'string', 'max:512'],
            'software_request_review_board_user_ids' => ['nullable', 'string', 'max:512'],
            'show_issue_category_on_request_form' => ['nullable', 'boolean'],
            'email_ticket_intake_enabled' => ['nullable', 'boolean'],
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
            'screen_agent_leaderboard_tickets_weight' => HelpdeskSetting::KEY_SCREEN_AGENT_TICKETS_WEIGHT,
            'screen_agent_leaderboard_response_weight' => HelpdeskSetting::KEY_SCREEN_AGENT_RESPONSE_WEIGHT,
            'screen_duty_station_items_per_page' => HelpdeskSetting::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE,
            'screen_category_items_per_page' => HelpdeskSetting::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE,
            'screen_list_slider_interval_seconds' => HelpdeskSetting::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS,
            'screen_support_group_slider_interval_seconds' => HelpdeskSetting::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS,
            'software_request_notify_group_ids' => HelpdeskSetting::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS,
            'software_request_review_board_user_ids' => HelpdeskSetting::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS,
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

        if (array_key_exists('requester_unsatisfied_follow_up_enabled', $validated) && $validated['requester_unsatisfied_follow_up_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP,
                $validated['requester_unsatisfied_follow_up_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('whatsapp_enabled', $validated) && $validated['whatsapp_enabled'] !== null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_WHATSAPP_ENABLED, $validated['whatsapp_enabled'] ? '1' : '0');
        }

        if (array_key_exists('teams_enabled', $validated) && $validated['teams_enabled'] !== null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_TEAMS_ENABLED, $validated['teams_enabled'] ? '1' : '0');
        }

        if (array_key_exists('agent_monthly_report_enabled', $validated) && $validated['agent_monthly_report_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED,
                $validated['agent_monthly_report_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('agent_monthly_report_email_enabled', $validated) && $validated['agent_monthly_report_email_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED,
                $validated['agent_monthly_report_email_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('agent_monthly_report_retention_months', $validated) && $validated['agent_monthly_report_retention_months'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS,
                (string) $validated['agent_monthly_report_retention_months']
            );
        }

        if (array_key_exists('resolved_auto_close_days', $validated) && $validated['resolved_auto_close_days'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_RESOLVED_AUTO_CLOSE_DAYS,
                (string) $validated['resolved_auto_close_days']
            );
        }

        if (array_key_exists('agent_open_ticket_reminder_enabled', $validated) && $validated['agent_open_ticket_reminder_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED,
                $validated['agent_open_ticket_reminder_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('license_expiry_alert_enabled', $validated) && $validated['license_expiry_alert_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED,
                $validated['license_expiry_alert_enabled'] ? '1' : '0'
            );
        }

        if (array_key_exists('license_expiry_alert_interval_days', $validated) && $validated['license_expiry_alert_interval_days'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS,
                (string) $validated['license_expiry_alert_interval_days']
            );
        }

        if (array_key_exists('show_issue_category_on_request_form', $validated) && $validated['show_issue_category_on_request_form'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM,
                $validated['show_issue_category_on_request_form'] ? '1' : '0'
            );
        }

        if (array_key_exists('email_ticket_intake_enabled', $validated) && $validated['email_ticket_intake_enabled'] !== null) {
            HelpdeskSetting::setValue(
                HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED,
                $validated['email_ticket_intake_enabled'] ? '1' : '0'
            );
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

        if (array_key_exists('faq_sources_json', $validated)) {
            $incoming = $validated['faq_sources_json'];
            if ($incoming === null || trim($incoming) === '') {
                HelpdeskSetting::setValue(HelpdeskSetting::KEY_FAQ_SOURCES_JSON, null);
            } else {
                try {
                    json_decode($incoming, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    return response()->json(['message' => 'FAQ sources must be valid JSON.'], 422);
                }
                HelpdeskSetting::setValue(HelpdeskSetting::KEY_FAQ_SOURCES_JSON, $incoming);
            }
        }

        return $this->show($request);
    }
}
