<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('approved')->after('is_active');
            $table->string('phone')->nullable()->after('email');
            $table->string('organisation')->nullable()->after('phone');
            $table->timestamp('approved_at')->nullable()->after('totp_required');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->string('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'approval_status',
                'phone',
                'organisation',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
