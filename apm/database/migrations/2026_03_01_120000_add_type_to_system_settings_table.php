<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Field type: text, password (masked in UI), number, boolean, color.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('system_settings', 'type')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->string('type', 20)->default('text')->after('group');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'type')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
