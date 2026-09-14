<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketHistory;

class TicketHistoryLogger
{
    public function log(HelpdeskTicket $ticket, string $event, ?int $userId, ?array $payload = null): void
    {
        HelpdeskTicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'event' => $event,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
