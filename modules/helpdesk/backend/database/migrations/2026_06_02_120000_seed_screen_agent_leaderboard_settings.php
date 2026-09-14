<?php

use App\Models\HelpdeskSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_SCREEN_AGENT_TICKETS_WEIGHT) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_SCREEN_AGENT_TICKETS_WEIGHT, '60');
        }

        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_SCREEN_AGENT_RESPONSE_WEIGHT) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_SCREEN_AGENT_RESPONSE_WEIGHT, '40');
        }
    }

    public function down(): void
    {
        HelpdeskSetting::query()
            ->whereIn('key', [
                HelpdeskSetting::KEY_SCREEN_AGENT_TICKETS_WEIGHT,
                HelpdeskSetting::KEY_SCREEN_AGENT_RESPONSE_WEIGHT,
            ])
            ->delete();
    }
};
