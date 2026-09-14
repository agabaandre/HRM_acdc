<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workplan_pra_settings')) {
            return;
        }

        Schema::create('workplan_pra_settings', function (Blueprint $table): void {
            $table->string('setting_key', 80)->primary();
            $table->json('setting_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workplan_pra_settings');
    }
};
