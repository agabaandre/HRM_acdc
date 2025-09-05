<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('special_memos', 'responsible_person_id')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->unsignedInteger('responsible_person_id')->nullable()->after('staff_id')->comment('ID of the responsible person for this special memo');
                $table->foreign('responsible_person_id')->references('staff_id')->on('staff')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropForeign(['responsible_person_id']);
            $table->dropColumn('responsible_person_id');
        });
    }
};
