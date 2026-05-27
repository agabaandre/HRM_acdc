<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            // NULL = unspecified. Valid values: 'remote', 'onsite'.
            $table->string('work_mode', 16)->nullable()->after('duty_station');
            $table->timestamp('work_mode_updated_at')->nullable()->after('work_mode');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn(['work_mode', 'work_mode_updated_at']);
        });
    }
};
