<?php

namespace App\Jobs;

use App\Models\HelpdeskTicket;
use App\Services\TicketAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Auto-assign end-user tickets after the create API has responded (avoids gateway timeouts).
 */
class AssignEndUserTicket
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public int $ticketId,
        public ?string $requesterDutyStation,
    ) {}

    public function handle(TicketAssignmentService $assignment): void
    {
        $ticket = HelpdeskTicket::query()->find($this->ticketId);
        if (! $ticket || $ticket->assigned_user_id || $ticket->assigned_group_id) {
            return;
        }

        $result = $assignment->assignAgent($ticket, $this->requesterDutyStation);
        if (! $result['user_id'] && ! $result['group_id']) {
            return;
        }

        $ticket->assigned_user_id = $result['user_id'];
        $ticket->assigned_group_id = $result['group_id'];
        $ticket->save();
    }
}
