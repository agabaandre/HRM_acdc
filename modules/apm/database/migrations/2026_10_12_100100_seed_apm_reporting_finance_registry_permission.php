<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->permissionsConnection();
        if ($connection === false) {
            return;
        }

        $db = $connection === null ? DB::connection() : DB::connection($connection);
        $schema = $connection === null ? Schema::connection(config('database.default')) : Schema::connection($connection);

        $name = 'apm_reporting_finance_registry';
        if ($db->table('permissions')->where('name', $name)->exists()) {
            return;
        }

        $row = [
            'name' => $name,
            'definition' => 'APM Reporting & Finance Registry',
        ];

        if ($schema->hasColumn('permissions', 'module')) {
            $row['module'] = 'apm';
        }

        $db->table('permissions')->insert($row);
    }

    public function down(): void
    {
        $connection = $this->permissionsConnection();
        if ($connection === false) {
            return;
        }

        $db = $connection === null ? DB::connection() : DB::connection($connection);
        $db->table('permissions')->where('name', 'apm_reporting_finance_registry')->delete();
    }

    /**
     * @return null|string|false null = default connection; string = named connection; false = unavailable
     */
    private function permissionsConnection(): null|string|false
    {
        try {
            if (Schema::hasTable('permissions')) {
                return null;
            }
        } catch (\Throwable) {
            // fall through
        }

        $staffDb = trim((string) config('database.connections.staff_app.database', ''));
        if ($staffDb === '') {
            return false;
        }

        try {
            if (Schema::connection('staff_app')->hasTable('permissions')) {
                return 'staff_app';
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
};
