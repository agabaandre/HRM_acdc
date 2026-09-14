<?php

use App\Models\HelpdeskSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED, '1');
        }

        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED, '1');
        }

        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS, '12');
        }
    }

    public function down(): void
    {
        HelpdeskSetting::query()
            ->whereIn('key', [
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_ENABLED,
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_EMAIL_ENABLED,
                HelpdeskSetting::KEY_AGENT_MONTHLY_REPORT_RETENTION_MONTHS,
            ])
            ->delete();
    }
};
