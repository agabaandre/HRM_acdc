<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_type', 32)->default('system_user')->after('user_id');
            $table->foreignId('external_integration_id')
                ->nullable()
                ->after('actor_type')
                ->constrained('external_integrations')
                ->nullOnDelete();
            $table->index(['actor_type', 'created_at']);
        });

        // Existing rows: anything with a user is a system user; leave default otherwise.
        if (Schema::hasColumn('audit_logs', 'actor_type')) {
            DB::table('audit_logs')->whereNotNull('user_id')->update(['actor_type' => 'system_user']);
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_type', 'created_at']);
            $table->dropConstrainedForeignId('external_integration_id');
            $table->dropColumn('actor_type');
        });
    }
};
