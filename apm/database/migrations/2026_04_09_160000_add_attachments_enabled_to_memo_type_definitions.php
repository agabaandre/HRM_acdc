<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->boolean('attachments_enabled')->default(false)->after('is_division_specific');
        });
    }

    public function down(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->dropColumn('attachments_enabled');
        });
    }
};
