<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'pra_create_enabled'],
            [
                'value' => '1',
                'group' => 'app',
                'type' => 'boolean',
            ]
        );
    }

    public function down(): void
    {
        SystemSetting::query()->where('key', 'pra_create_enabled')->delete();
        \Illuminate\Support\Facades\Cache::forget('system_setting:pra_create_enabled');
    }
};
