<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->timestamp('suspicious_resolved_at')->nullable()->after('suspicious_reasons');
            $table->foreignId('suspicious_resolved_by')
                ->nullable()
                ->after('suspicious_resolved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('suspicious_resolution_note')->nullable()->after('suspicious_resolved_by');
            $table->index(['is_suspicious', 'suspicious_resolved_at'], 'audit_logs_suspicious_resolution_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_suspicious_resolution_idx');
            $table->dropConstrainedForeignId('suspicious_resolved_by');
            $table->dropColumn(['suspicious_resolved_at', 'suspicious_resolution_note']);
        });
    }
};
