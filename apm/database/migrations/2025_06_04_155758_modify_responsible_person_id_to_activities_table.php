<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyResponsiblePersonIdToActivitiesTable extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Adjust to match staff.staff_id type; assuming it's unsigned INT
            $table->unsignedInteger('responsible_person_id')->nullable()->after('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('responsible_person_id');
        });
    }
}
