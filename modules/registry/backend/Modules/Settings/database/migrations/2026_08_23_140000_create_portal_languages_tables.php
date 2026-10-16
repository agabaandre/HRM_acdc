<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_languages')) {
            Schema::create('portal_languages', function (Blueprint $table): void {
                $table->id();
                $table->string('locale_code', 32)->unique();
                $table->string('name', 120);
                $table->string('google_translate_code', 32)->nullable();
                $table->string('flag_emoji', 16)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('portal_ui_translations')) {
            Schema::create('portal_ui_translations', function (Blueprint $table): void {
                $table->id();
                $table->string('locale_code', 32);
                $table->string('group_key', 64);
                $table->string('item_key', 120);
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['locale_code', 'group_key', 'item_key'], 'portal_ui_translations_unique');
            });
        }

        $now = now();
        $languages = [
            ['locale_code' => 'en', 'name' => 'English', 'google_translate_code' => 'en', 'flag_emoji' => '🇬🇧', 'sort_order' => 10],
            ['locale_code' => 'fr', 'name' => 'Français', 'google_translate_code' => 'fr', 'flag_emoji' => '🇫🇷', 'sort_order' => 20],
            ['locale_code' => 'ar', 'name' => 'العربية', 'google_translate_code' => 'ar', 'flag_emoji' => '🇸🇦', 'sort_order' => 30],
            ['locale_code' => 'es', 'name' => 'Español', 'google_translate_code' => 'es', 'flag_emoji' => '🇪🇸', 'sort_order' => 40],
            ['locale_code' => 'pt', 'name' => 'Português', 'google_translate_code' => 'pt', 'flag_emoji' => '🇵🇹', 'sort_order' => 50],
            ['locale_code' => 'sw', 'name' => 'Kiswahili', 'google_translate_code' => 'sw', 'flag_emoji' => '🇰🇪', 'sort_order' => 60],
        ];

        foreach ($languages as $row) {
            $exists = DB::table('portal_languages')->where('locale_code', $row['locale_code'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('portal_languages')->insert([
                ...$row,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_ui_translations');
        Schema::dropIfExists('portal_languages');
    }
};
