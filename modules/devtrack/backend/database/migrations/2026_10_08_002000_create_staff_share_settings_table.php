<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_share_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('base_url')->default('https://cbp.africacdc.org/staff/backend');
            $table->text('api_token')->nullable()->comment('Encrypted Bearer / STAFF_API_TOKEN');
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('last_sync_total')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_share_settings');
    }
};
