<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_module_settings')) {
            Schema::create('portal_module_settings', function (Blueprint $table): void {
                $table->string('module_key', 40)->primary();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $defaults = [
            'dashboard' => true,
            'staff' => true,
            'leave' => true,
            'payroll' => false,
            'performance' => true,
            'tasks' => true,
            'workplan' => true,
            'admanager' => true,
            'settings' => true,
        ];

        foreach ($defaults as $key => $enabled) {
            $exists = DB::table('portal_module_settings')->where('module_key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('portal_module_settings')->insert([
                'module_key' => $key,
                'is_enabled' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_module_settings');
    }
};
