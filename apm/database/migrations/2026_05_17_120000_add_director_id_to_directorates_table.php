<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directorates', function (Blueprint $table) {
            if (! Schema::hasColumn('directorates', 'director_id')) {
                $table->unsignedInteger('director_id')->nullable()->after('is_active');
                $table->index('director_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('directorates', function (Blueprint $table) {
            if (Schema::hasColumn('directorates', 'director_id')) {
                $table->dropColumn('director_id');
            }
        });
    }
};
