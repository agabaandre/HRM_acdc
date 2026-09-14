<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_codes', function (Blueprint $table) {
            $table->year('year')->after('id'); // adjust 'after' as appropriate
        });
    }

    public function down(): void
    {
        Schema::table('fund_codes', function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }
};
