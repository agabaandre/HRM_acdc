<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores secondary division IDs for access (from staff_contracts.other_associated_divisions).
     */
    public function up(): void
    {
        if (Schema::hasColumn('staff', 'associated_divisions')) {
            return;
        }
        Schema::table('staff', function (Blueprint $table) {
            $table->json('associated_divisions')->nullable()->after('division_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('staff', 'associated_divisions')) {
            return;
        }
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('associated_divisions');
        });
    }
};
