<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->boolean('admin_logo_inverse')->default(false)->after('logo_dark_path');
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->dropColumn('admin_logo_inverse');
        });
    }
};
