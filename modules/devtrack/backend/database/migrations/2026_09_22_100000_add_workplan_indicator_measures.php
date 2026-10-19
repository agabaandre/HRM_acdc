<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workplan_indicators', function (Blueprint $table) {
            $table->string('baseline')->nullable()->after('unit');
            $table->string('q1')->nullable()->after('baseline');
            $table->string('q2')->nullable()->after('q1');
            $table->string('q3')->nullable()->after('q2');
            $table->string('q4')->nullable()->after('q3');
        });
    }

    public function down(): void
    {
        Schema::table('workplan_indicators', function (Blueprint $table) {
            $table->dropColumn(['baseline', 'q1', 'q2', 'q3', 'q4']);
        });
    }
};
