<?php

use App\Models\HelpdeskSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (HelpdeskSetting::getValue(HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP) === null) {
            HelpdeskSetting::setValue(HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP, '1');
        }
    }

    public function down(): void
    {
        HelpdeskSetting::query()
            ->where('key', HelpdeskSetting::KEY_REQUESTER_UNSATISFIED_FOLLOW_UP)
            ->delete();
    }
};
