<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->boolean('cc_on_approval_enabled')->default(false)->after('attachments_enabled');
            $table->string('cc_all_staff_heading', 500)->nullable()->after('cc_on_approval_enabled');
            $table->string('cc_all_staff_label', 255)->default('All Africa CDC Staff')->after('cc_all_staff_heading');
        });
    }

    public function down(): void
    {
        Schema::table('memo_type_definitions', function (Blueprint $table) {
            $table->dropColumn(['cc_on_approval_enabled', 'cc_all_staff_heading', 'cc_all_staff_label']);
        });
    }
};
