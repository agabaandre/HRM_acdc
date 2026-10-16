<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('rr_settings')->insert([
            ['key' => 'import_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('rr_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename', 255);
            $table->string('stored_path', 512);
            $table->string('status', 32)->default('preview'); // preview|matched_committed|completed|cancelled
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('staged_count')->default(0);
            $table->unsignedInteger('mapped_imported_count')->default(0);
            $table->json('unmatched_summary')->nullable();
            $table->unsignedInteger('created_by_staff_id')->nullable();
            $table->timestamps();
        });

        Schema::create('rr_import_staged_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('rr_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('excel_row')->nullable();
            $table->string('business_unit', 255)->nullable()->index();
            $table->json('payload'); // normalized risk fields ready for insert
            $table->string('status', 32)->default('pending'); // pending|imported|skipped
            $table->unsignedInteger('mapped_division_id')->nullable();
            $table->unsignedInteger('mapped_directorate_id')->nullable();
            $table->unsignedBigInteger('imported_risk_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_import_staged_rows');
        Schema::dropIfExists('rr_import_batches');
        Schema::dropIfExists('rr_settings');
    }
};
