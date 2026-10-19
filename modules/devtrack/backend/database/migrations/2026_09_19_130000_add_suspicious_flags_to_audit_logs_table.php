<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->boolean('is_suspicious')->default(false)->after('user_agent');
            $table->text('suspicious_reasons')->nullable()->after('is_suspicious');
            $table->index(['is_suspicious', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['is_suspicious', 'created_at']);
            $table->dropColumn(['is_suspicious', 'suspicious_reasons']);
        });
    }
};
