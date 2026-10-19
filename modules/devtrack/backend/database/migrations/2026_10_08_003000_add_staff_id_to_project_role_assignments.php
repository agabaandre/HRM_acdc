<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('user_id')
                ->constrained('staff')
                ->nullOnDelete();
        });

        // Make user_id nullable without doctrine/dbal.
        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE project_role_assignments MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE project_role_assignments ALTER COLUMN user_id DROP NOT NULL');
        }

        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
        });

        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE project_role_assignments MODIFY user_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE project_role_assignments ALTER COLUMN user_id SET NOT NULL');
        }

        Schema::table('project_role_assignments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
