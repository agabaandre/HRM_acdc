<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_databases', function (Blueprint $table) {
            $table->json('exclude_tables')->nullable()->after('description')
                ->comment('Tables omitted from mysqldump (e.g. large log tables)');
        });

        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->text('monthly_archive_emails')->nullable()
                ->comment('Comma-separated recipients for end-of-month backup archive email');
            $table->boolean('monthly_archive_enabled')->default(true);
            $table->unsignedSmallInteger('monthly_attachment_max_mb')->default(20);
            $table->timestamps();
        });

        DB::table('backup_settings')->insert([
            'monthly_archive_emails' => null,
            'monthly_archive_enabled' => true,
            'monthly_attachment_max_mb' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('backup_databases')) {
            DB::table('backup_databases')
                ->where('name', 'staff')
                ->update([
                    'exclude_tables' => json_encode(['user_logs', 'access_sessions']),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('backup_databases', function (Blueprint $table) {
            $table->dropColumn('exclude_tables');
        });

        Schema::dropIfExists('backup_settings');
    }
};
