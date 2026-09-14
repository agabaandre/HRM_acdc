<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('referenced_memos_max')->default(0)->after('cc_on_approval_enabled');
        });

        Schema::table('other_memos', function (Blueprint $table) {
            $table->unsignedTinyInteger('referenced_memos_max_snapshot')->default(0)->after('cc_config');
            $table->json('referenced_memos')->nullable()->after('referenced_memos_max_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->dropColumn(['referenced_memos', 'referenced_memos_max_snapshot']);
        });

        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->dropColumn('referenced_memos_max');
        });
    }
};
