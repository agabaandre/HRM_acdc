<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_ticket_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['helpdesk_ticket_id', 'user_id']);
            $table->index(['user_id', 'helpdesk_ticket_id']);
        });

        if (Schema::hasTable('helpdesk_tickets')) {
            DB::table('helpdesk_tickets')
                ->whereNotNull('assigned_user_id')
                ->orderBy('id')
                ->chunk(200, function ($rows): void {
                    $now = now();
                    foreach ($rows as $row) {
                        DB::table('helpdesk_ticket_assignees')->insertOrIgnore([
                            'helpdesk_ticket_id' => $row->id,
                            'user_id' => $row->assigned_user_id,
                            'is_primary' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ticket_assignees');
    }
};
