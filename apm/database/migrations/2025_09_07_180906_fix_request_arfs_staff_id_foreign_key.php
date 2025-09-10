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
        // Drop the existing foreign key constraint for staff_id
        DB::statement('ALTER TABLE request_arfs DROP FOREIGN KEY request_arfs_staff_id_foreign');
        
        // Add the correct foreign key constraint referencing staff.staff_id
        DB::statement('ALTER TABLE request_arfs ADD CONSTRAINT request_arfs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the corrected foreign key constraint
        DB::statement('ALTER TABLE request_arfs DROP FOREIGN KEY request_arfs_staff_id_foreign');
        
        // Restore the original foreign key constraint (if needed)
        DB::statement('ALTER TABLE request_arfs ADD CONSTRAINT request_arfs_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE');
    }
};