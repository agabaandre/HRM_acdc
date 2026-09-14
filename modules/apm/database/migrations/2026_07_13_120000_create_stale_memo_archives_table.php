<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stale_memo_archives', function (Blueprint $table) {
            $table->id();
            $table->string('memo_type', 40);
            $table->unsignedBigInteger('memo_id');
            $table->string('document_number', 100)->nullable();
            $table->string('title', 500)->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('responsible_person_id')->nullable();
            $table->decimal('budget_total', 14, 2)->default(0);
            $table->string('previous_status', 40)->nullable();
            $table->timestamp('memo_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->string('trigger', 20)->default('scheduled');
            $table->unsignedBigInteger('archived_by_staff_id')->nullable();
            $table->timestamps();

            $table->index(['memo_type', 'memo_id']);
            $table->index('archived_at');
            $table->index('staff_id');
        });

        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'budget_stale_draft_auto_archive_enabled'],
            [
                'value' => '1',
                'group' => 'budget',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stale_memo_archives');

        DB::table('system_settings')->where('key', 'budget_stale_draft_auto_archive_enabled')->delete();
    }
};
