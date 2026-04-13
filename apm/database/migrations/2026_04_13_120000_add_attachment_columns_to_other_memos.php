<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->boolean('attachments_enabled_snapshot')->default(false)->after('fields_schema_snapshot');
            $table->json('attachment')->nullable()->after('attachments_enabled_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('other_memos', function (Blueprint $table) {
            $table->dropColumn(['attachments_enabled_snapshot', 'attachment']);
        });
    }
};
