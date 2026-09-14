<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->unsignedInteger('staff_portal_role')->nullable()->after('staff_id');
            $table->json('staff_portal_permissions')->nullable()->after('staff_portal_role');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn(['staff_portal_role', 'staff_portal_permissions']);
        });
    }
};
