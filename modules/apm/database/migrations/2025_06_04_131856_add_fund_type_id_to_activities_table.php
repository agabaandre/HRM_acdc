<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFundTypeIdToActivitiesTable extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['fund_type_id']);
            $table->dropColumn('fund_type_id');
        });
    }
}
