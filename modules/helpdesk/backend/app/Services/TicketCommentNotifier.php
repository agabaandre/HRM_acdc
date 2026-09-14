<?php

namespace App\Services;

use App\Mail\TicketRequesterCommentMail;
use App\Mail\TicketStaffReplyMail;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketCommentNotifier
{
    public function notifyAssigneeOnRequesterComment(
        HelpdeskTicket $ticket,
        HelpdeskTicketComment $comment,
        User $requester,
        bool $ticketReopened = false,
    ): void {
        if (! $ticket->assigned_user_id) {
            return;
        }

        $assignee = User::query()->with('helpdeskProfile')->find((int) $ticket->assigned_user_id);
        if (! $assignee || ! $this->shouldNotifyAssignee($assignee)) {
            return;
        }

        $email = trim((string) $assignee->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new TicketRequesterCommentMail(
                $ticket->fresh(['category']),
                $comment,
                $requester,
                $ticketReopened,
            ));
        } catch (\Throwable $e) {
            Log::warning('helpdesk.requester_comment_mail_failed', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'assignee_user_id' => $assignee->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Email the requester when a helpdesk agent posts a public (non-internal) reply.
     */
    public function notifyRequesterOnStaffComment(
        HelpdeskTicket $ticket,
        HelpdeskTicketComment $comment,
        User $agent,
    ): void {
        $email = trim((string) $ticket->requester_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost:5174'), '/');
        $ticketUrl = $frontend.'/tickets/'.$ticket->id;

        try {
            Mail::to($email)->send(new TicketStaffReplyMail(
                $ticket->fresh(['category']),
                $comment,
                $agent,
                $ticketUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('helpdesk.staff_reply_mail_failed', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'agent_user_id' => $agent->id,
                'requester_email' => $email,
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
