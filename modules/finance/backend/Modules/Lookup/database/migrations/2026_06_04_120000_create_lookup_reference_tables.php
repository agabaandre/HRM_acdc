<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table): void {
                $table->id();
                $table->string('region_name', 100);
            });
        }

        if (! Schema::hasTable('nationalities')) {
            Schema::create('nationalities', function (Blueprint $table): void {
                $table->increments('nationality_id');
                $table->string('nationality', 50);
                $table->string('nationality_name', 50)->nullable();
                $table->string('continent', 50);
                $table->unsignedInteger('region_id');
                $table->string('iso2', 2)->nullable();
                $table->string('iso3', 3)->nullable();
            });
        }

        if (! Schema::hasTable('contract_types')) {
            Schema::create('contract_types', function (Blueprint $table): void {
                $table->increments('contract_type_id');
                $table->string('contract_type', 50);
            });
        }

        if (! Schema::hasTable('status')) {
            Schema::create('status', function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status', 50);
            });
        }
    }

    public function down(): void
    {
        // Legacy shared DB — do not drop production tables.
    }
};
