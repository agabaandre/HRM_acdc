<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->boolean('is_division_specific')->default(false)->after('ref_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->dropColumn('is_division_specific');
        });
    }
};
