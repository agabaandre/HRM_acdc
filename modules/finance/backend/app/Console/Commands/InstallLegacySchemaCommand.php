<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallLegacySchemaCommand extends Command
{
    protected $signature = 'staff-portal:install-legacy-schema
                            {--force : Run even when STAFF_LEGACY_SCHEMA_SKIP is true}';

    protected $description = 'Create missing staff portal tables from database/schema/staff-legacy-structure.sql (safe on existing DBs)';

    public function handle(): int
    {
        if (env('STAFF_LEGACY_SCHEMA_SKIP', false) && ! $this->option('force')) {
            $this->warn('STAFF_LEGACY_SCHEMA_SKIP=true — skipping. Use --force to run anyway.');

            return self::SUCCESS;
        }

        $path = database_path('schema/staff-legacy-structure.sql');
        if (! is_file($path)) {
            $this->error("Schema file not found: {$path}");

            return self::FAILURE;
        }

        $sql = (string) file_get_contents($path);
        $statements = preg_split('/;\s*\n/', $sql) ?: [];
        $created = 0;
        $skipped = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            if (! preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?/i', $statement, $m)) {
                continue;
            }
            $table = $m[1];
            if (Schema::hasTable($table)) {
                $skipped++;

                continue;
            }
            DB::unprepared($statement.';');
            $created++;
            $this->line("Created table: {$table}");
        }

        $this->info("Done. Created {$created} tables, skipped {$skipped} existing.");

        return self::SUCCESS;
    }
}
