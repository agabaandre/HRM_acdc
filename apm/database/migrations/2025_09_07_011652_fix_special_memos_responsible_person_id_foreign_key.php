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
        // Check if the column exists
        if (!Schema::hasColumn('special_memos', 'responsible_person_id')) {
            // Column doesn't exist, create it with the correct type
            Schema::table('special_memos', function (Blueprint $table) {
                $table->integer('responsible_person_id')->nullable()->after('staff_id')->comment('ID of the responsible person for this special memo');
            });
        } else {
            // Column exists, modify its type to match staff.staff_id
            \DB::statement('ALTER TABLE special_memos MODIFY COLUMN responsible_person_id INT NULL');
        }
        
        // Add the foreign key constraint using raw SQL
        try {
            \DB::statement('ALTER TABLE special_memos ADD CONSTRAINT special_memos_responsible_person_id_foreign FOREIGN KEY (responsible_person_id) REFERENCES staff(staff_id) ON DELETE SET NULL');
        } catch (\Exception $e) {
            // Constraint might already exist, continue
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint using raw SQL
        \DB::statement('ALTER TABLE special_memos DROP FOREIGN KEY special_memos_responsible_person_id_foreign');
        
        // Drop the column
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn('responsible_person_id');
        });
    }
};
