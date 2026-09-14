<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if created_at column exists, if not create it
        if (!Schema::hasColumn('divisions', 'created_at')) {
            Schema::table('divisions', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable()->after('category');
            });
        }

        // Check if updated_at column exists, if not create it
        if (!Schema::hasColumn('divisions', 'updated_at')) {
            Schema::table('divisions', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }

        // If both columns exist, we need to reorder them
        if (Schema::hasColumn('divisions', 'created_at') && Schema::hasColumn('divisions', 'updated_at')) {
            // Use raw SQL to reorder columns since Laravel doesn't have a direct method
            DB::statement('ALTER TABLE divisions MODIFY COLUMN created_at TIMESTAMP NULL AFTER category');
            DB::statement('ALTER TABLE divisions MODIFY COLUMN updated_at TIMESTAMP NULL AFTER created_at');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reorder back to original position (updated_at before created_at)
        if (Schema::hasColumn('divisions', 'created_at') && Schema::hasColumn('divisions', 'updated_at')) {
            DB::statement('ALTER TABLE divisions MODIFY COLUMN updated_at TIMESTAMP NULL AFTER category');
            DB::statement('ALTER TABLE divisions MODIFY COLUMN created_at TIMESTAMP NULL AFTER updated_at');
        }
    }
};