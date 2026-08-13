<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_email_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver', 32);
            $table->json('config')->nullable();
            $table->string('from_address')->default('');
            $table->string('from_name')->default('');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['driver', 'is_active']);
        });

        if (Schema::hasTable('payroll_payslips') && ! Schema::hasColumn('payroll_payslips', 'emailed_at')) {
            Schema::table('payroll_payslips', function (Blueprint $table) {
                $table->timestamp('emailed_at')->nullable()->after('generated_at');
            });
        }

        $now = now();
        $config = array_filter([
            'tenant_id' => env('EXCHANGE_TENANT_ID'),
            'client_id' => env('EXCHANGE_CLIENT_ID'),
            'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
            'scope' => env('EXCHANGE_SCOPE', 'https://graph.microsoft.com/.default'),
            'auth_method' => env('EXCHANGE_AUTH_METHOD', 'client_credentials'),
            'redirect_uri' => env('EXCHANGE_REDIRECT_URI'),
        ], fn ($v) => $v !== null && $v !== '');

        DB::table('portal_email_providers')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'Microsoft Exchange',
            'slug' => 'microsoft-exchange',
            'driver' => 'exchange',
            'config' => json_encode($config),
            'from_address' => (string) env('MAIL_FROM_ADDRESS', 'notifications@africacdc.org'),
            'from_name' => (string) env('MAIL_FROM_NAME', 'Staff Portal'),
            'description' => 'Default Exchange / Microsoft Graph mailer. Values fall back to EXCHANGE_* and MAIL_* env when empty.',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_payslips') && Schema::hasColumn('payroll_payslips', 'emailed_at')) {
            Schema::table('payroll_payslips', function (Blueprint $table) {
                $table->dropColumn('emailed_at');
            });
        }
        Schema::dropIfExists('portal_email_providers');
    }
};
