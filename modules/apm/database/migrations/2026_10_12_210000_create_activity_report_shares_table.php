<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->string('target', 16); // rba | rbm
            $table->string('status', 32)->default('queued'); // queued|sent|failed
            $table->unsignedBigInteger('shared_by')->nullable();
            $table->text('note')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();

            $table->index(['target', 'status']);
            $table->index(['activity_report_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_report_shares');
    }
};
