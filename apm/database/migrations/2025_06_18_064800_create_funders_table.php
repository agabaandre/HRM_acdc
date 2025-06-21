<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Insert default funders directly in the migration
        DB::table('funders')->insert([
            ['name' => 'World Bank'],
            ['name' => 'Gavi'],
            ['name' => 'Member State'],
            ['name' => 'AfDB'],
            ['name' => 'US CDC'],
            ['name' => 'CEPI'],
            ['name' => 'ECDC'],
            ['name' => 'UNICEF'],
            ['name' => 'KDCA'],
            ['name' => 'WDF'],
            ['name' => 'Korea-Hepatitis'],
            ['name' => 'UNAIDS'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('funders');
    }
};

