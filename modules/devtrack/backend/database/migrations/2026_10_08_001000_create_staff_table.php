<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique()->comment('External Staff Share API staff_id');
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('work_email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->string('division_name')->nullable();
            $table->unsignedBigInteger('directorate_id')->nullable()->index();
            $table->string('directorate_name')->nullable();
            $table->json('associated_divisions')->nullable();
            $table->json('other_associated_divisions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable()->comment('Full Staff Share API row');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
