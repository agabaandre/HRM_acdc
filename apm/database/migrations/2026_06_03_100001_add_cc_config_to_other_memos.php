<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->boolean('cc_on_approval_enabled_snapshot')->default(false)->after('attachments_enabled_snapshot');
            $table->json('cc_config')->nullable()->after('cc_on_approval_enabled_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->dropColumn(['cc_on_approval_enabled_snapshot', 'cc_config']);
        });
    }
};
