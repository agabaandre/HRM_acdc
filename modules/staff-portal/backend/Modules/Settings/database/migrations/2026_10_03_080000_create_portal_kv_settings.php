<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_kv_settings')) {
            Schema::create('portal_kv_settings', function (Blueprint $table): void {
                $table->string('setting_key', 120)->primary();
                $table->text('setting_value')->nullable();
                $table->string('group', 64)->default('general')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_kv_settings');
    }
};
