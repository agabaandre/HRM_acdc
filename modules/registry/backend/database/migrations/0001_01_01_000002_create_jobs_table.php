<?php

use App\Support\LegacyDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel queue tables — prefixed to avoid CI3 `jobs` (staff positions).
     */
    public function up(): void
    {
        $queueTable = (string) config('staff-portal.queue.jobs_table', 'sp_queue_jobs');
        $batchesTable = (string) config('staff-portal.queue.batches_table', 'sp_job_batches');
        $failedTable = (string) config('staff-portal.queue.failed_table', 'sp_failed_jobs');

        if (! Schema::hasTable($queueTable)) {
            Schema::create($queueTable, function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable($batchesTable)) {
            Schema::create($batchesTable, function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable($failedTable)) {
            Schema::create($failedTable, function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (LegacyDatabase::hasLegacyJobsTable()) {
            return;
        }

        Schema::dropIfExists(config('staff-portal.queue.jobs_table', 'sp_queue_jobs'));
        Schema::dropIfExists(config('staff-portal.queue.batches_table', 'sp_job_batches'));
        Schema::dropIfExists(config('staff-portal.queue.failed_table', 'sp_failed_jobs'));
    }
};
