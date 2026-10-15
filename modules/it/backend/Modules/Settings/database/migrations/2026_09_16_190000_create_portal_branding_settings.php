<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_branding_settings')) {
            Schema::create('portal_branding_settings', function (Blueprint $table): void {
                $table->string('setting_key', 64)->primary();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            'company_name' => 'Africa CDC',
            'system_logo' => '/assets/images/AU_CDC_Logo-800.png',
            'footer_copyright' => 'Copyright © Africa CDC {year}. All rights reserved.',
            'print_footer' => "Africa CDC Headquarters, Ring Road, 16/17,\nHaile Garment Lafto Square, Nifas Silk-Lafto Sub City,\nP.O Box: 200050 Addis Ababa",
            'company_email' => 'registry@africacdc.org',
            'company_phone' => '',
            'company_website' => 'https://africacdc.org',
            'company_address' => "Africa CDC Headquarters, Ring Road, 16/17,\nHaile Garment Lafto Square, Nifas Silk-Lafto Sub City,\nP.O Box: 200050 Addis Ababa",
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            $exists = DB::table('portal_branding_settings')->where('setting_key', $key)->exists();
            if ($exists) {
                continue;
            }
            DB::table('portal_branding_settings')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_branding_settings');
    }
};
