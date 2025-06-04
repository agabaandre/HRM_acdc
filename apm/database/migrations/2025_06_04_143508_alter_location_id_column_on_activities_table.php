<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AlterLocationIdColumnOnActivitiesTable extends Migration
{
    public function up(): void
    {
        // MySQL does not support altering a JSON column directly with Laravel Schema,
        // so we use raw SQL.
        DB::statement("ALTER TABLE activities MODIFY location_id JSON NOT NULL DEFAULT ('[]')");
    }

    public function down(): void
    {
        // Revert the column to NOT NULL without default
        DB::statement("ALTER TABLE activities MODIFY location_id JSON NOT NULL");
    }
}

