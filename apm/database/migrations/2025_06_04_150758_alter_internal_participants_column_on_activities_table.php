<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterInternalParticipantsColumnOnActivitiesTable extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE activities MODIFY internal_participants JSON NOT NULL DEFAULT ('[]')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE activities MODIFY internal_participants JSON NOT NULL");
    }
}
