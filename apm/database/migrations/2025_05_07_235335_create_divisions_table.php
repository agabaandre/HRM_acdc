<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            // $table->string('name');
            $table->json('staff_ids')->nullable();
            $table->boolean('is_external')->default(false);
            $table->foreignId('directorate_id')->nullable();
            $table->boolean('is_active')->default(true);

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
