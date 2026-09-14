<?php

namespace App\Services;

use App\Mail\TicketInProgressMail;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketStatusNotifier
{
    /**
     * Email the requester when an agent moves the ticket to in progress (e.g. Kanban board).
     */
    public function notifyIfMovedToInProgress(HelpdeskTicket $ticket): void
    {
        if (! $ticket->wasChanged('status')) {
            return;
        }

        if ($ticket->status !== 'in_progress') {
            return;
        }

        $previous = (string) $ticket->getOriginal('status');
        if ($previous === 'in_progress') {
            return;
        }

        $email = trim((string) $ticket->requester_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $agent = null;
        if ($ticket->assigned_user_id) {
            $agent = User::query()->find((int) $ticket->assigned_user_id);
        }

        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
        $ticketUrl = $frontend.'/tickets/'.$ticket->id;

        try {
            Mail::to($email)->send(new TicketInProgressMail(
                $ticket->fresh(['category']),
                $agent,
                $ticketUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('helpdesk.in_progress_mail_failed', [
                'ticket_id' => $ticket->id,
                'requester_email' => $email,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
