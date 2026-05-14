<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_audit_logs', function (Blueprint $table) {
            $table->string('correlation_id', 36)->nullable()->after('user_agent')->index();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_audit_logs', function (Blueprint $table) {
            $table->dropColumn('correlation_id');
        });
    }
};
