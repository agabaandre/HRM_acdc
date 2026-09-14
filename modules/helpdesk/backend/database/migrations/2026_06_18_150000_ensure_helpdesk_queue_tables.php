<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net when queue config expected `jobs` but migrations created helpdesk_* tables,
 * or when the shared-DB legacy guard skipped queue table creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $queueTable = (string) env('DB_QUEUE_TABLE', 'helpdesk_queue_jobs');
        $batchesTable = (string) env('DB_QUEUE_BATCHES_TABLE', 'helpdesk_job_batches');
        $failedTable = (string) env('DB_QUEUE_FAILED_TABLE', 'helpdesk_failed_jobs');

        if (! Schema::hasTable($queueTable)) {
            Schema::create($queueTable, function (Blueprint $table) {
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
            Schema::create($batchesTable, function (Blueprint $table) {
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
            Schema::create($failedTable, function (Blueprint $table) {
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
        // Intentionally no-op: queue tables may be shared or populated in production.
    }
};
