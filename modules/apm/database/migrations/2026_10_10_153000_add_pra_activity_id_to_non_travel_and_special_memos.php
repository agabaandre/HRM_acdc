<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('non_travel_memos') && ! Schema::hasColumn('non_travel_memos', 'pra_activity_id')) {
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->unsignedBigInteger('pra_activity_id')->nullable()->after('workplan_activity_code');
                $table->index('pra_activity_id');
            });
        }

        if (Schema::hasTable('special_memos') && ! Schema::hasColumn('special_memos', 'pra_activity_id')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->unsignedBigInteger('pra_activity_id')->nullable()->after('workplan_activity_code');
                $table->index('pra_activity_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('non_travel_memos') && Schema::hasColumn('non_travel_memos', 'pra_activity_id')) {
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->dropIndex(['pra_activity_id']);
                $table->dropColumn('pra_activity_id');
            });
        }

        if (Schema::hasTable('special_memos') && Schema::hasColumn('special_memos', 'pra_activity_id')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->dropIndex(['pra_activity_id']);
                $table->dropColumn('pra_activity_id');
            });
        }
    }
};
