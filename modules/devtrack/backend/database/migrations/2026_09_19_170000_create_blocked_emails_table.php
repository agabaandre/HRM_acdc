<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->text('reason');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('email');
            $table->index(['is_active', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_emails');
    }
};
