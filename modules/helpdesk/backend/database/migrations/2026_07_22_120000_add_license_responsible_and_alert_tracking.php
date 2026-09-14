<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_licenses', 'responsible_staff_id')) {
                $table->unsignedInteger('responsible_staff_id')->nullable()->after('created_by_user_id')->index();
            }
            if (! Schema::hasColumn('helpdesk_licenses', 'expiry_alert_last_sent_at')) {
                $table->timestamp('expiry_alert_last_sent_at')->nullable()->after('warning_days_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_licenses', 'expiry_alert_last_sent_at')) {
                $table->dropColumn('expiry_alert_last_sent_at');
            }
            if (Schema::hasColumn('helpdesk_licenses', 'responsible_staff_id')) {
                $table->dropColumn('responsible_staff_id');
            }
        });
    }
};
