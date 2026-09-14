<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_types')) {
            return;
        }
        if (! Schema::hasColumn('contract_types', 'category')) {
            Schema::table('contract_types', function (Blueprint $table): void {
                $table->string('category', 32)->default('main_staff')->after('contract_type');
            });
        }
        DB::table('contract_types')->whereNull('category')->orWhere('category', '')->update(['category' => 'main_staff']);
        DB::table('contract_types')->whereNotIn('category', ['main_staff', 'other_staff'])->update(['category' => 'main_staff']);
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_types') && Schema::hasColumn('contract_types', 'category')) {
            Schema::table('contract_types', function (Blueprint $table): void {
                $table->dropColumn('category');
            });
        }
    }
};
