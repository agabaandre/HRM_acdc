<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_work_mode_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_profile_id')->constrained('helpdesk_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->date('work_date');
            $table->string('first_work_mode', 16);
            $table->string('last_work_mode', 16);
            $table->unsignedInteger('switch_count')->default(1);
            $table->timestamp('first_set_at');
            $table->timestamp('last_set_at');
            $table->timestamps();

            $table->unique(['helpdesk_profile_id', 'work_date'], 'helpdesk_work_mode_trails_profile_date_unique');
            $table->index(['work_date', 'last_work_mode'], 'helpdesk_work_mode_trails_date_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_work_mode_trails');
    }
};
