<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the table exists and handle the structure
        if (Schema::hasTable('matrix_approval_trails')) {
            // Rename the table first
            Schema::rename('matrix_approval_trails', 'approval_trails');
        }

        // Ensure the table exists
        if (!Schema::hasTable('approval_trails')) {
            Schema::create('approval_trails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('matrix_id')->nullable();
                $table->foreignId('model_id')->nullable();
                $table->string('model_type')->nullable();
                $table->unsignedBigInteger('staff_id');
                $table->unsignedBigInteger('oic_staff_id')->nullable();
                $table->string('action');
                $table->text('remarks')->nullable();
                $table->unsignedInteger('approval_order')->nullable();
                $table->timestamps();
            });
        } else {
            // Modify existing table structure safely
            Schema::table('approval_trails', function (Blueprint $table) {
                // Make matrix_id nullable if it exists
                if (Schema::hasColumn('approval_trails', 'matrix_id')) {
                    $table->foreignId('matrix_id')->nullable()->change();
                }

                // Add polymorphic relationship columns only if they don't exist
                if (!Schema::hasColumn('approval_trails', 'model_id')) {
                    $table->foreignId('model_id')->nullable()->after('matrix_id');
                }
                if (!Schema::hasColumn('approval_trails', 'model_type')) {
                    $table->string('model_type')->nullable()->after('model_id');
                }

                // Add approval_order column if it doesn't exist
                if (!Schema::hasColumn('approval_trails', 'approval_order')) {
                    $table->unsignedInteger('approval_order')->nullable()->after('remarks');
                }
            });
        }

        // Update existing records to use polymorphic relationships
        DB::statement("UPDATE approval_trails SET model_id = matrix_id, model_type = 'App\\\\Models\\\\Matrix' WHERE matrix_id IS NOT NULL AND (model_id IS NULL OR model_id = 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the polymorphic changes
        Schema::table('approval_trails', function (Blueprint $table) {
            if (Schema::hasColumn('approval_trails', 'model_id')) {
                $table->dropColumn('model_id');
            }
            if (Schema::hasColumn('approval_trails', 'model_type')) {
                $table->dropColumn('model_type');
            }
            if (Schema::hasColumn('approval_trails', 'approval_order')) {
                $table->dropColumn('approval_order');
            }
        });

        // Rename back to original table name
        Schema::rename('approval_trails', 'matrix_approval_trails');
    }
};
