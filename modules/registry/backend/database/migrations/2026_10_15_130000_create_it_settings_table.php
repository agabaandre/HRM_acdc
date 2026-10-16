<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('it_settings')->insert([
            [
                'key' => 'license_expiry_alert_enabled',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'license_expiry_alert_interval_days',
                'value' => '7',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'license_expiry_alert_intervals',
                'value' => '1,3,7',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('it_settings');
    }
};
