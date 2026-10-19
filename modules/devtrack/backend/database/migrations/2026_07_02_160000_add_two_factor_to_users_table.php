<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_email_enabled')->default(false)->after('is_active');
            $table->boolean('two_factor_totp_enabled')->default(false)->after('two_factor_email_enabled');
            $table->text('two_factor_totp_secret')->nullable()->after('two_factor_totp_enabled');
            $table->json('two_factor_totp_recovery_codes')->nullable()->after('two_factor_totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_email_enabled',
                'two_factor_totp_enabled',
                'two_factor_totp_secret',
                'two_factor_totp_recovery_codes',
            ]);
        });
    }
};
