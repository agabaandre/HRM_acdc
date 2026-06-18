<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->boolean('grant_helpdesk_admin')->default(false)->after('can_reassign_tickets');
            $table->boolean('grant_supervisor_access')->default(false)->after('grant_helpdesk_admin');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn(['grant_helpdesk_admin', 'grant_supervisor_access']);
        });
    }
};
