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
        // Check if audit_logs table exists and has resource_id column
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'resource_id')) {
            // Add entity_id column if it doesn't exist
            if (!Schema::hasColumn('audit_logs', 'entity_id')) {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->string('entity_id')->nullable()->after('resource_id');
                });
            }
            
            // Copy resource_id values to entity_id for existing records
            DB::statement('UPDATE audit_logs SET entity_id = CAST(resource_id AS CHAR) WHERE entity_id IS NULL AND resource_id IS NOT NULL');
        }
        
        // Ensure all audit tables have consistent structure
        $this->ensureAuditTableStructure('audit_logs');
        $this->ensureAuditTableStructure('audit_funders_logs');
        $this->ensureAuditTableStructure('audit_users_logs');
        
        // Check for any other audit tables that might exist
        $tables = DB::select('SHOW TABLES');
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            if (strpos($tableName, 'audit_') === 0 && strpos($tableName, '_logs') !== false && $tableName !== 'audit_logs') {
                $this->ensureAuditTableStructure($tableName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove entity_id column from audit_logs if it was added
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'entity_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn('entity_id');
            });
        }
    }
    
    /**
     * Ensure audit table has the required structure
     */
    private function ensureAuditTableStructure(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }
        
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Add entity_id if it doesn't exist
            if (!Schema::hasColumn($tableName, 'entity_id')) {
                $table->string('entity_id')->nullable()->after('id');
            }
            
            // Add action column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'action')) {
                $table->string('action')->nullable();
            }
            
            // Add old_values column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'old_values')) {
                $table->json('old_values')->nullable();
            }
            
            // Add new_values column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'new_values')) {
                $table->json('new_values')->nullable();
            }
            
            // Add causer_type column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'causer_type')) {
                $table->string('causer_type')->nullable();
            }
            
            // Add causer_id column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'causer_id')) {
                $table->string('causer_id')->nullable();
            }
            
            // Add metadata column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'metadata')) {
                $table->json('metadata')->nullable();
            }
            
            // Add created_at column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            
            // Add source column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'source')) {
                $table->string('source')->nullable();
            }
        });
        
        // For audit_logs table, copy resource_id to entity_id if needed
        if ($tableName === 'audit_logs' && Schema::hasColumn($tableName, 'resource_id')) {
            DB::statement("UPDATE {$tableName} SET entity_id = CAST(resource_id AS CHAR) WHERE entity_id IS NULL AND resource_id IS NOT NULL");
        }
    }
};