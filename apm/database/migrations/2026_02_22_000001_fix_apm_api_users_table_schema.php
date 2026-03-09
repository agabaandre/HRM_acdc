<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix apm_api_users table: if it has the wrong schema (e.g. id/staff_id instead of user_id),
     * drop and recreate with the schema expected by ApmApiUser and the users sync.
     */
    public function up(): void
    {
        if (!Schema::hasTable('apm_api_users')) {
            $this->createTable();
            return;
        }

        $columns = Schema::getColumnListing('apm_api_users');
        if (in_array('user_id', $columns, true)) {
            return;
        }

        Schema::dropIfExists('apm_api_users');
        $this->createTable();
    }

    private function createTable(): void
    {
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
