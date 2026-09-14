<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_request', function (Blueprint $table) {
            if (! Schema::hasColumn('change_request', 'previous_overall_status')) {
                $table->string('previous_overall_status')->nullable()->after('overall_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('change_request', function (Blueprint $table) {
            if (Schema::hasColumn('change_request', 'previous_overall_status')) {
                $table->dropColumn('previous_overall_status');
            }
        });
    }
};
