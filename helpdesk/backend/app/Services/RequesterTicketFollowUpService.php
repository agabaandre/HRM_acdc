<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;

class RequesterTicketFollowUpService
{
    /** @var list<string> */
    public const CLOSED_STATUSES = ['closed', 'resolved', 'awaiting_requester_confirmation'];

    public function __construct(
        private readonly TicketCommentNotifier $notifier,
        private readonly TicketHistoryLogger $logger,
    ) {}

    public function isClosedStatus(string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }

    /**
     * Reopen a closed ticket for the requester and notify the assignee in the same comment email.
     */
    public function commentAndMaybeReopen(
        HelpdeskTicket $ticket,
        User $requester,
        HelpdeskProfile $profile,
        string $body,
        bool $requestReopen,
        bool $isInternal = false,
    ): array {
        $statusBefore = (string) $ticket->status;

        $comment = $ticket->comments()->create([
            'user_id' => $requester->id,
            'author_staff_id' => $profile->staff_id,
            'is_internal' => $isInternal,
            'body' => $body,
        ]);

        $ticketReopened = false;
        if (
            ! $isInternal
            && $profile->role === HelpdeskProfile::ROLE_USER
            && $requestReopen
            && HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()
            && $this->isClosedStatus($statusBefore)
        ) {
            $ticketReopened = $this->reopenTicket($ticket, $requester, $statusBefore);
        }

        if (
            ! $isInternal
            && $profile->role === HelpdeskProfile::ROLE_USER
            && HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()
        ) {
            $this->notifier->notifyAssigneeOnRequesterComment(
                $ticket,
                $comment,
                $requester,
                $ticketReopened,
            );
        }

        return [
            'comment' => $comment,
            'ticket_reopened' => $ticketReopened,
        ];
    }

    public function reopenTicket(
        HelpdeskTicket $ticket,
        User $user,
        ?string $previousStatus = null,
        string $via = 'requester_comment',
    ): bool {
        $previousStatus ??= (string) $ticket->status;

        if (! $this->isClosedStatus($previousStatus)) {
            return false;
        }

        $ticket->forceFill([
            'status' => 'open',
            'closed_at' => null,
            'resolved_at' => null,
            'resolution_confirmed_at' => null,
            'resolution_confirm_token' => null,
        ])->save();

        $this->logger->log($ticket, 'ticket.reopened', $user->id, [
            'previous_status' => $previousStatus,
            'via' => $via,
        ]);

        return true;
    }
}
