<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When false (default), email+password login is rejected for APM API and should match Staff user.allow_email_login (synced from /share/users).
     */
    public function up(): void
    {
        if (!Schema::hasTable('apm_api_users')) {
            return;
        }
        if (Schema::hasColumn('apm_api_users', 'allow_email_login')) {
            return;
        }
        Schema::table('apm_api_users', function (Blueprint $table) {
            $table->boolean('allow_email_login')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('apm_api_users') || !Schema::hasColumn('apm_api_users', 'allow_email_login')) {
            return;
        }
        Schema::table('apm_api_users', function (Blueprint $table) {
            $table->dropColumn('allow_email_login');
        });
    }
};
