<?php

namespace App\Services;

use App\Mail\TicketAssignedToAgentMail;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketAssignmentNotifier
{
    /**
     * Call from {@see HelpdeskTicket} `created` when the row was inserted with an assignee.
     */
    public function notifyOnInitialAssign(HelpdeskTicket $ticket): void
    {
        if (! $ticket->assigned_user_id) {
            return;
        }
        $this->sendToAssignee($ticket, false);
    }

    /**
     * Call from {@see HelpdeskTicket} `updated` when `assigned_user_id` changed to a non-null user.
     */
    public function notifyIfAssigneeChanged(HelpdeskTicket $ticket): void
    {
        if (! $ticket->wasChanged('assigned_user_id') || ! $ticket->assigned_user_id) {
            return;
        }
        $previousId = $ticket->getOriginal('assigned_user_id');
        $isReassignment = $previousId !== null && (int) $previousId !== (int) $ticket->assigned_user_id;

        $this->sendToAssignee($ticket, $isReassignment);
    }

    private function sendToAssignee(HelpdeskTicket $ticket, bool $isReassignment): void
    {
        $assignee = User::query()->with('helpdeskProfile')->find((int) $ticket->assigned_user_id);
        if (! $assignee || ! $this->shouldNotifyAssignee($assignee)) {
            return;
        }

        $email = trim((string) $assignee->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new TicketAssignedToAgentMail(
                $ticket->fresh(['category']),
                $assignee,
                $isReassignment,
            ));
        } catch (\Throwable $e) {
            Log::warning('helpdesk.assignment_mail_failed', [
                'ticket_id' => $ticket->id,
                'assignee_user_id' => $assignee->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function shouldNotifyAssignee(User $user): bool
    {
        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }

        return in_array($p->role, [
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
        ], true);
    }
}
