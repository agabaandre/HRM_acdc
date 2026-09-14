<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->boolean('show_activity_code')->default(false)->after('is_active');
        });

        DB::table('funders')->whereIn('id', [1, 2, 6])->update(['show_activity_code' => true]);
    }

    public function down(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->dropColumn('show_activity_code');
        });
    }
};
