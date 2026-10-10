<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'pra_activity_id')) {
                $table->unsignedBigInteger('pra_activity_id')->nullable()->after('workplan_activity_code');
                $table->index('pra_activity_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'pra_activity_id')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['pra_activity_id']);
            $table->dropColumn('pra_activity_id');
        });
    }
};
