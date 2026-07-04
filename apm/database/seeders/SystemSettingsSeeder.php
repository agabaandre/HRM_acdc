<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Default system settings (branding from cbp.africacdc.org / APM home).
     * Primary/secondary colors match the sign-in and app theme.
     */
    public function run(): void
    {
        $settings = [
            // Branding (group: branding)
            ['key' => 'primary_color', 'value' => '#119a48', 'group' => 'branding'],
            ['key' => 'secondary_color', 'value' => '#1bb85a', 'group' => 'branding'],
            ['key' => 'primary_color_dark', 'value' => '#0d7a3a', 'group' => 'branding'],
            ['key' => 'logo', 'value' => '/assets/images/logo.png', 'group' => 'branding'],
            ['key' => 'logo_dark', 'value' => '', 'group' => 'branding'],
            ['key' => 'favicon', 'value' => '/favicon.ico', 'group' => 'branding'],

            // App (group: app)
            ['key' => 'application_name', 'value' => 'Africa CDC APM', 'group' => 'app'],
            ['key' => 'application_short_name', 'value' => 'APM', 'group' => 'app'],
            ['key' => 'tagline', 'value' => 'Approval & Programme Management', 'group' => 'app'],
            ['key' => 'support_email', 'value' => 'support@africacdc.org', 'group' => 'app'],
            ['key' => 'footer_text', 'value' => '© ' . date('Y') . ' Africa CDC. All rights reserved.', 'group' => 'app'],

            // Locale & format (group: locale)
            ['key' => 'default_currency', 'value' => 'USD', 'group' => 'locale'],
            ['key' => 'currency_symbol', 'value' => '$', 'group' => 'locale'],
            ['key' => 'timezone', 'value' => 'Africa/Nairobi', 'group' => 'locale'],
            ['key' => 'date_format', 'value' => 'd M Y', 'group' => 'locale'],
            ['key' => 'date_time_format', 'value' => 'd M Y H:i', 'group' => 'locale'],
            ['key' => 'locale', 'value' => 'en', 'group' => 'locale'],

            // UI (group: ui)
            ['key' => 'items_per_page', 'value' => '15', 'group' => 'ui'],
            ['key' => 'pagination_size', 'value' => '10', 'group' => 'ui'],
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'ui'],

            // Approvals (group: approvals) — aging reminder threshold (days at your level)
            ['key' => 'approval_warning_days', 'value' => '7', 'group' => 'approvals', 'type' => 'number'],

            // Budget commitment (group: budget)
            ['key' => 'budget_draft_max_age_months', 'value' => '2', 'group' => 'budget', 'type' => 'number'],
            ['key' => 'budget_committed_activity_statuses', 'value' => 'draft,pending,submitted,approved', 'group' => 'budget', 'type' => 'text'],
            ['key' => 'budget_committed_memo_statuses', 'value' => 'draft,pending,approved', 'group' => 'budget', 'type' => 'text'],
            ['key' => 'budget_committed_change_request_statuses', 'value' => 'draft,pending,submitted', 'group' => 'budget', 'type' => 'text'],
            ['key' => 'budget_stale_draft_reminders_enabled', 'value' => '1', 'group' => 'budget', 'type' => 'boolean'],

            // Service requests (group: service_requests)
            ['key' => 'allow_child_service_requests', 'value' => '1', 'group' => 'service_requests', 'type' => 'boolean'],
        ];

        foreach ($settings as $item) {
            SystemSetting::updateOrCreate(
                ['key' => $item['key']],
                [
                    'value' => $item['value'],
                    'group' => $item['group'],
                    'type' => $item['type'] ?? 'text',
                ]
            );
        }
    }
}
