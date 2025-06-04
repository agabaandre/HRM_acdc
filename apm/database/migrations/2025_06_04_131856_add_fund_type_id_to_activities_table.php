<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFundTypeIdToActivitiesTable extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('fund_type_id')->nullable()->after('status');

            $table->foreign('fund_type_id')
                ->references('id')
                ->on('fund_types')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['fund_type_id']);
            $table->dropColumn('fund_type_id');
        });
    }
}
