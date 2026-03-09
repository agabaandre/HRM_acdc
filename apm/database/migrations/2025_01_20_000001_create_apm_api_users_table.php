<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * APM API users table: same structure as staff app `user` table,
     * plus email (for API login), last_used_at, remember_token, updated_at.
     */
    public function up(): void
    {
        if (Schema::hasTable('apm_api_users')) {
            return;
        }
        Schema::create('apm_api_users', function (Blueprint $table) {
            $table->integer('user_id')->primary()->comment('Matches source user.user_id');
            $table->string('password', 255)->nullable();
            $table->string('name', 50)->nullable();
            $table->string('role', 255);
            $table->unsignedInteger('auth_staff_id')->comment('Links to staff.staff_id');
            $table->boolean('status')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->date('changed')->nullable();
            $table->integer('isChanged')->default(0);
            $table->string('photo', 200)->nullable()->default('author.png');
            $table->string('signature', 100)->nullable();
            $table->integer('is_approved')->default(0);
            $table->integer('is_verfied')->default(0);
            $table->string('langauge', 100)->default('en');
            // API-specific
            $table->string('email')->nullable()->comment('From staff.work_email for API login');
            $table->timestamp('last_used_at')->nullable();
            $table->rememberToken();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apm_api_users');
    }
};
