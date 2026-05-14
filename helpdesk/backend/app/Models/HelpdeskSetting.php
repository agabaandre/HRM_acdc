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

    public const KEY_BRANDING_PRIMARY = 'branding_primary_hex';

    public const KEY_BRANDING_SECONDARY = 'branding_secondary_hex';

    /** Comma-separated Staff division_id values; users in these divisions become Helpdesk agents on SSO unless admin. */
    public const KEY_DEFAULT_AGENT_DIVISION_IDS = 'default_agent_division_ids';

    /** When "1", requester must confirm via link before status becomes resolved. */
    public const KEY_REQUIRE_RESOLUTION_CONFIRMATION = 'require_resolution_confirmation';

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

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function requireResolutionConfirmation(): bool
    {
        $v = static::getValue(self::KEY_REQUIRE_RESOLUTION_CONFIRMATION);
        if ($v === null || $v === '') {
            return true;
        }

        return in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
    }

    public static function aiAgentAssignmentEnabled(): bool
    {
        $v = static::getValue(self::KEY_AI_AGENT_ASSIGNMENT, '0');

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
