<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_ticket_assignees')) {
            Schema::create('helpdesk_ticket_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('helpdesk_ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['helpdesk_ticket_id', 'user_id']);
                $table->index(['user_id', 'helpdesk_ticket_id']);
            });

            $this->backfillFromTickets();

            return;
        }

        if (Schema::hasColumn('helpdesk_ticket_assignees', 'ticket_id')
            && ! Schema::hasColumn('helpdesk_ticket_assignees', 'helpdesk_ticket_id')) {
            Schema::table('helpdesk_ticket_assignees', function (Blueprint $table) {
                $table->renameColumn('ticket_id', 'helpdesk_ticket_id');
            });
        }

        if (! Schema::hasColumn('helpdesk_ticket_assignees', 'is_primary')) {
            Schema::table('helpdesk_ticket_assignees', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('user_id');
            });

            DB::table('helpdesk_ticket_assignees as a')
                ->join('helpdesk_tickets as t', 't.id', '=', 'a.helpdesk_ticket_id')
                ->whereColumn('a.user_id', 't.assigned_user_id')
                ->update(['a.is_primary' => true]);
        }

        $this->backfillFromTickets();
    }

    public function down(): void
    {
        // Non-destructive alignment migration — no rollback.
    }

    private function backfillFromTickets(): void
    {
        if (! Schema::hasTable('helpdesk_tickets') || ! Schema::hasColumn('helpdesk_ticket_assignees', 'helpdesk_ticket_id')) {
            return;
        }

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
};
