<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_provider_id')->nullable()->constrained('email_providers')->nullOnDelete();
            $table->foreignId('external_integration_id')->nullable()->constrained('external_integrations')->nullOnDelete();
            $table->string('to');
            $table->string('subject');
            $table->string('status'); // sent, failed
            $table->text('error_message')->nullable();
            $table->string('driver')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
