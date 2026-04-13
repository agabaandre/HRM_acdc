<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->boolean('is_division_specific_snapshot')->nullable()->after('division_id');
            $table->string('division_code_snapshot', 64)->nullable()->after('is_division_specific_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->dropColumn(['is_division_specific_snapshot', 'division_code_snapshot']);
        });
    }
};
