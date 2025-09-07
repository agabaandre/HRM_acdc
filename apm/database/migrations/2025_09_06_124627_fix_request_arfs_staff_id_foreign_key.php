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
        // Check if the column exists and what type it is
        $columnExists = Schema::hasColumn('request_arfs', 'staff_id');
        
        if ($columnExists) {
            // Get the current column type
            $columns = \DB::select("SHOW COLUMNS FROM request_arfs LIKE 'staff_id'");
            
            if (!empty($columns)) {
                $currentType = $columns[0]->Type;
                
                // Only proceed if the column is not already the correct type
                if (strpos($currentType, 'int') === false || strpos($currentType, 'unsigned') !== false) {
                    // Check if foreign key constraint exists and drop it safely
                    $foreignKeys = \DB::select("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'request_arfs' 
                        AND COLUMN_NAME = 'staff_id' 
                        AND CONSTRAINT_NAME != 'PRIMARY'
                    ");
                    
                    if (!empty($foreignKeys)) {
                        \DB::statement('ALTER TABLE request_arfs DROP FOREIGN KEY ' . $foreignKeys[0]->CONSTRAINT_NAME);
                    }
                    
                    // Modify the column to match staff.staff_id data type (integer)
                    \DB::statement('ALTER TABLE request_arfs MODIFY COLUMN staff_id INT NOT NULL');
                    
                    // Add the foreign key constraint using raw SQL
                    try {
                        \DB::statement('ALTER TABLE request_arfs ADD CONSTRAINT request_arfs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE');
                    } catch (\Exception $e) {
                        // Constraint might already exist, continue
                        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                            throw $e;
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint using raw SQL
        try {
            \DB::statement('ALTER TABLE request_arfs DROP FOREIGN KEY request_arfs_staff_id_foreign');
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }
        
        // Change the column type back to bigint unsigned
        \DB::statement('ALTER TABLE request_arfs MODIFY COLUMN staff_id BIGINT UNSIGNED NOT NULL');
        
        // Add back the original foreign key constraint (if needed)
        try {
            \DB::statement('ALTER TABLE request_arfs ADD CONSTRAINT request_arfs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            // Constraint might already exist, continue
        }
    }
};