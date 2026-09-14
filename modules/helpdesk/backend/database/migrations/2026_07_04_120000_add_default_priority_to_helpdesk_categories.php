<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_categories', function (Blueprint $table) {
            $table->string('default_priority', 24)->default('medium')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_categories', function (Blueprint $table) {
            $table->dropColumn('default_priority');
        });
    }
};
