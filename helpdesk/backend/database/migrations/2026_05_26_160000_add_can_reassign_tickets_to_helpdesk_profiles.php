<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-agent "Can reassign tickets" permission. Admins bypass this flag
 * (canReassignTickets() returns true for ROLE_ADMIN regardless). NULL is
 * treated as false so existing rows stay locked out by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->boolean('can_reassign_tickets')->nullable()->default(null)->after('can_manage_kb');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn('can_reassign_tickets');
        });
    }
};
