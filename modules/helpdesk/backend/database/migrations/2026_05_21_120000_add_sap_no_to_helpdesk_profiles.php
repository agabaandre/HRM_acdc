<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->string('sap_no', 64)->nullable()->after('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn('sap_no');
        });
    }
};
