<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('helpdesk_settings')->updateOrInsert(
            ['key' => 'show_category_ai_description_on_request_form'],
            ['value' => '1', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('helpdesk_settings')->where('key', 'show_category_ai_description_on_request_form')->delete();
    }
};
