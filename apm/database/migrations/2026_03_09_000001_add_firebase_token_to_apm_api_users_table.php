<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add firebase_token for FCM push notifications to API (mobile) users.
     */
    public function up(): void
    {
        Schema::table('apm_api_users', function (Blueprint $table) {
            $table->string('firebase_token', 512)->nullable()->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apm_api_users', function (Blueprint $table) {
            $table->dropColumn('firebase_token');
        });
    }
};
