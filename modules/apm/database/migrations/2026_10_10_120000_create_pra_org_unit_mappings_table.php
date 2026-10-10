<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pra_org_unit_mappings')) {
            return;
        }

        Schema::create('pra_org_unit_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('pra_code', 64);
            $table->string('pra_name')->nullable();
            $table->string('entity_type', 32)->default('division'); // division|directorate
            $table->unsignedBigInteger('local_division_id')->nullable();
            $table->unsignedBigInteger('local_directorate_id')->nullable();
            $table->string('match_source', 32)->default('manual'); // auto|alias|manual
            $table->timestamps();

            $table->unique('pra_code');
            $table->index('local_division_id');
            $table->index('local_directorate_id');
            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pra_org_unit_mappings');
    }
};
