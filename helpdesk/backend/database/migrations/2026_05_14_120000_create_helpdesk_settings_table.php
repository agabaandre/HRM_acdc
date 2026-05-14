<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'ai_provider' => 'openai',
            'ai_api_endpoint' => 'https://api.openai.com/v1',
            'ai_api_key' => null,
            'ai_model_name' => 'gpt-4o-mini',
            'ai_active' => '0',
            'ai_fallback_order' => 'openai',
            'branding_primary_hex' => '#0d7a3a',
            'branding_secondary_hex' => '#c9a227',
        ];
        foreach ($defaults as $key => $value) {
            DB::table('helpdesk_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_settings');
    }
};
