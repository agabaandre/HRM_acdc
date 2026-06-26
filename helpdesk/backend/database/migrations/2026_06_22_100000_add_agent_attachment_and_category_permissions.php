<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->boolean('can_delete_request_attachments')->default(false)->after('can_reassign_tickets');
            $table->boolean('can_change_ticket_category')->default(false)->after('can_delete_request_attachments');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn(['can_delete_request_attachments', 'can_change_ticket_category']);
        });
    }
};
