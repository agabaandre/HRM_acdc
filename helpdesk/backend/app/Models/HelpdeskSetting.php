<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpdeskSetting extends Model
{
    protected $table = 'helpdesk_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public const KEY_AI_PROVIDER = 'ai_provider';

    public const KEY_AI_API_ENDPOINT = 'ai_api_endpoint';

    public const KEY_AI_API_KEY = 'ai_api_key';

    public const KEY_AI_MODEL_NAME = 'ai_model_name';

    public const KEY_AI_ACTIVE = 'ai_active';

    /** When "1" and AI is configured, end-user tickets may use AI to choose among eligible agents before rule-based fallback. */
    public const KEY_AI_AGENT_ASSIGNMENT = 'ai_agent_assignment_enabled';

    public const KEY_AI_FALLBACK_ORDER = 'ai_fallback_order';

    /** JSON array of external FAQ ingest sources (APM, other systems). */
    public const KEY_FAQ_SOURCES_JSON = 'faq_sources_json';

    /** JSON summary of the last FAQ ingest run. */
    public const KEY_FAQ_INGEST_LAST_RESULT = 'faq_ingest_last_result';

    public const KEY_BRANDING_PRIMARY = 'branding_primary_hex';

    public const KEY_BRANDING_SECONDARY = 'branding_secondary_hex';

    /** Comma-separated Staff division_id values; users in these divisions become Helpdesk agents on SSO unless admin. */
    public const KEY_DEFAULT_AGENT_DIVISION_IDS = 'default_agent_division_ids';

    /** Deprecated: resolution now closes the ticket and emails a review link. Kept for API compatibility. */
    public const KEY_REQUIRE_RESOLUTION_CONFIRMATION = 'require_resolution_confirmation';

    /**
     * When "1" (default), requesters on closed tickets may reopen via a comment checkbox and
     * assigned agents receive email when a requester posts a public comment.
     */
    public const KEY_REQUESTER_UNSATISFIED_FOLLOW_UP = 'requester_unsatisfied_follow_up_enabled';

    /** WhatsApp Cloud API — see https://developers.facebook.com/docs/whatsapp/cloud-api */
    public const KEY_WHATSAPP_ENABLED = 'whatsapp_enabled';

    public const KEY_WHATSAPP_PHONE_NUMBER_ID = 'whatsapp_phone_number_id';

    public const KEY_WHATSAPP_ACCESS_TOKEN = 'whatsapp_access_token';

    public const KEY_WHATSAPP_APP_SECRET = 'whatsapp_app_secret';

    public const KEY_WHATSAPP_VERIFY_TOKEN = 'whatsapp_verify_token';

    /** Microsoft Teams / Azure Bot — see https://learn.microsoft.com/en-us/azure/bot-service/bot-service-overview-introduction */
    public const KEY_TEAMS_ENABLED = 'teams_enabled';

    public const KEY_TEAMS_APP_ID = 'teams_app_id';

    public const KEY_TEAMS_TENANT_ID = 'teams_tenant_id';

    public const KEY_TEAMS_APP_PASSWORD = 'teams_app_password';

    public const KEY_TEAMS_MESSAGING_PATH = 'teams_messaging_path';

    /** Lobby screen agent-of-week/month scoring — percentage weights (default 60 / 40). */
    public const KEY_SCREEN_AGENT_TICKETS_WEIGHT = 'screen_agent_leaderboard_tickets_weight';

    public const KEY_SCREEN_AGENT_RESPONSE_WEIGHT = 'screen_agent_leaderboard_response_weight';

    /** Lobby screen — duty station table items per slider page. */
    public const KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE = 'screen_duty_station_items_per_page';

    /** Lobby screen — category list items per slider page. */
    public const KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE = 'screen_category_items_per_page';

    /** Lobby screen — seconds between duty station / category slider pages. */
    public const KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS = 'screen_list_slider_interval_seconds';

    /** Lobby screen — seconds between support-group agent-of-week slides. */
    public const KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS = 'screen_support_group_slider_interval_seconds';

    public const KEY_AGENT_MONTHLY_REPORT_ENABLED = 'agent_monthly_report_enabled';

    public const KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED = 'agent_monthly_report_email_enabled';

    public const KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS = 'agent_monthly_report_retention_months';

    /** Days after resolution before unresolved tickets auto-close (ITIL closure). 0 = disabled. */
    public const KEY_RESOLVED_AUTO_CLOSE_DAYS = 'resolved_auto_close_days';

    /** Daily email to agents who still have open / in-progress tickets assigned. */
    public const KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED = 'agent_open_ticket_reminder_enabled';

    /** Email responsible person + helpdesk admins when licenses enter their warning window. */
    public const KEY_LICENSE_EXPIRY_ALERT_ENABLED = 'license_expiry_alert_enabled';

    /** Days between repeated license expiry alert emails (1, 3, or 7). */
    public const KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS = 'license_expiry_alert_interval_days';

    /** Comma-separated support group IDs notified on new software requests. */
    public const KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS = 'software_request_notify_group_ids';

    /** Comma-separated agent user IDs on the software request review board. */
    public const KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS = 'software_request_review_board_user_ids';

    /**
     * When "1", request form shows issue category (chained under business unit).
     * When "0" (default), only business unit is shown and AI categorizes asynchronously.
     */
    public const KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM = 'show_issue_category_on_request_form';

    /**
     * When "1" (default), category cards on the create form show AI description text.
     * When "0", only category names are shown.
     */
    public const KEY_SHOW_CATEGORY_AI_DESCRIPTION_ON_REQUEST_FORM = 'show_category_ai_description_on_request_form';

    /**
     * When "1" (default), tickets created by an eligible agent are assigned to that agent
     * when they match Agents & support groups routing for the ticket category.
     * When "0", creator is excluded from the pool and normal routing applies.
     */
    public const KEY_ASSIGN_AGENT_CREATED_TICKETS_TO_CREATOR = 'assign_agent_created_tickets_to_creator';

    /** Master switch: poll Business Unit mailboxes and create tickets from email. */
    public const KEY_EMAIL_TICKET_INTAKE_ENABLED = 'email_ticket_intake_enabled';

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function showIssueCategoryOnRequestForm(): bool
    {
        $v = static::getValue(self::KEY_SHOW_ISSUE_CATEGORY_ON_REQUEST_FORM, '0');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function showCategoryAiDescriptionOnRequestForm(): bool
    {
        $v = static::getValue(self::KEY_SHOW_CATEGORY_AI_DESCRIPTION_ON_REQUEST_FORM, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function assignAgentCreatedTicketsToCreator(): bool
    {
        $v = static::getValue(self::KEY_ASSIGN_AGENT_CREATED_TICKETS_TO_CREATOR, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    /** Global gate for Exchange mailbox → ticket intake (per-BU flags still apply). */
    public static function emailTicketIntakeEnabled(): bool
    {
        $v = static::getValue(self::KEY_EMAIL_TICKET_INTAKE_ENABLED, '0');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    /** @deprecated Confirmation step removed; tickets close on agent resolution. */
    public static function requireResolutionConfirmation(): bool
    {
        return false;
    }

    public static function aiAgentAssignmentEnabled(): bool
    {
        $v = static::getValue(self::KEY_AI_AGENT_ASSIGNMENT, '0');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function requesterUnsatisfiedFollowUpEnabled(): bool
    {
        $v = static::getValue(self::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    /**
     * @return array{tickets: int, response: int}
     */
    public static function screenAgentLeaderboardWeights(): array
    {
        $tickets = (int) static::getValue(self::KEY_SCREEN_AGENT_TICKETS_WEIGHT, '60');
        $response = (int) static::getValue(self::KEY_SCREEN_AGENT_RESPONSE_WEIGHT, '40');

        $tickets = max(0, min(100, $tickets));
        $response = max(0, min(100, $response));

        if ($tickets + $response === 0) {
            return ['tickets' => 60, 'response' => 40];
        }

        return ['tickets' => $tickets, 'response' => $response];
    }

    public static function screenDutyStationItemsPerPage(): int
    {
        $v = (int) static::getValue(self::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE, '3');

        return max(1, min(20, $v));
    }

    public static function screenCategoryItemsPerPage(): int
    {
        $v = (int) static::getValue(self::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE, '3');

        return max(1, min(20, $v));
    }

    public static function screenListSliderIntervalSeconds(): int
    {
        $v = (int) static::getValue(self::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS, '5');

        return max(2, min(60, $v));
    }

    public static function screenSupportGroupSliderIntervalSeconds(): int
    {
        $v = (int) static::getValue(self::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS, '6');

        return max(2, min(60, $v));
    }

    /**
     * @return array{
     *     duty_station_items_per_page: int,
     *     category_items_per_page: int,
     *     list_slider_interval_seconds: int,
     *     support_group_slider_interval_seconds: int
     * }
     */
    public static function screenDisplayConfig(): array
    {
        return [
            'duty_station_items_per_page' => self::screenDutyStationItemsPerPage(),
            'category_items_per_page' => self::screenCategoryItemsPerPage(),
            'list_slider_interval_seconds' => self::screenListSliderIntervalSeconds(),
            'support_group_slider_interval_seconds' => self::screenSupportGroupSliderIntervalSeconds(),
        ];
    }

    public static function agentMonthlyReportEnabled(): bool
    {
        $v = static::getValue(self::KEY_AGENT_MONTHLY_REPORT_ENABLED, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function agentMonthlyReportEmailEnabled(): bool
    {
        $v = static::getValue(self::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function agentMonthlyReportRetentionMonths(): int
    {
        $v = (int) static::getValue(self::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS, '12');

        return max(1, min(120, $v));
    }

    /** @return int Days before auto-closing resolved tickets; 0 disables auto-close. */
    public static function resolvedAutoCloseDays(): int
    {
        $v = (int) static::getValue(self::KEY_RESOLVED_AUTO_CLOSE_DAYS, '7');

        return max(0, min(90, $v));
    }

    public static function agentOpenTicketReminderEnabled(): bool
    {
        $v = static::getValue(self::KEY_AGENT_OPEN_TICKET_REMINDER_ENABLED, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function licenseExpiryAlertEnabled(): bool
    {
        $v = static::getValue(self::KEY_LICENSE_EXPIRY_ALERT_ENABLED, '1');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function licenseExpiryAlertIntervalDays(): int
    {
        $v = (int) static::getValue(self::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS, '7');
        if (! in_array($v, [1, 3, 7], true)) {
            return 7;
        }

        return $v;
    }

    /**
     * @return list<int>
     */
    public static function softwareRequestNotifyGroupIds(): array
    {
        return self::parseIdList(static::getValue(self::KEY_SOFTWARE_REQUEST_NOTIFY_GROUP_IDS, ''));
    }

    /**
     * @return list<int>
     */
    public static function softwareRequestReviewBoardUserIds(): array
    {
        return self::parseIdList(static::getValue(self::KEY_SOFTWARE_REQUEST_REVIEW_BOARD_USER_IDS, ''));
    }

    /**
     * @return list<int>
     */
    private static function parseIdList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim($raw)) ?: [] as $part) {
            if ($part === '' || ! ctype_digit($part)) {
                continue;
            }
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
