<?php

use App\Models\HelpdeskSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            HelpdeskSetting::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE => '3',
            HelpdeskSetting::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE => '3',
            HelpdeskSetting::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS => '5',
            HelpdeskSetting::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS => '6',
        ];

        foreach ($defaults as $key => $value) {
            if (HelpdeskSetting::getValue($key) === null) {
                HelpdeskSetting::setValue($key, $value);
            }
        }
    }

    public function down(): void
    {
        HelpdeskSetting::query()
            ->whereIn('key', [
                HelpdeskSetting::KEY_SCREEN_DUTY_STATION_ITEMS_PER_PAGE,
                HelpdeskSetting::KEY_SCREEN_CATEGORY_ITEMS_PER_PAGE,
                HelpdeskSetting::KEY_SCREEN_LIST_SLIDER_INTERVAL_SECONDS,
                HelpdeskSetting::KEY_SCREEN_SUPPORT_GROUP_SLIDER_INTERVAL_SECONDS,
            ])
            ->delete();
    }
};
