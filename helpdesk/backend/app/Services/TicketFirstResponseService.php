<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class TicketFirstResponseService
{
    /**
     * Persist the timestamp of the first public reply from support staff.
     */
    public function recordFromComment(HelpdeskTicket $ticket, HelpdeskProfile $author, bool $isInternal): void
    {
        if ($isInternal || $author->role === HelpdeskProfile::ROLE_USER) {
            return;
        }

        $this->markIfEmpty($ticket);
        $ticket->save();
    }

    /**
     * Set first_response_at on the model when still empty (caller saves when batching updates).
     */
    public function markIfEmpty(HelpdeskTicket $ticket, ?DateTimeInterface $at = null): void
    {
        if ($ticket->first_response_at !== null) {
            return;
        }

        $ticket->first_response_at = $at ?? now();
    }

    /**
     * Average minutes from ticket creation to first staff reply (public comments only).
     * Uses stored first_response_at when present, otherwise derives from comment history
     * or staff resolution time.
     */
    public function averageFirstResponseMinutesSince(DateTimeInterface $since, ?int $businessUnitId = null): ?int
    {
        $sinceStr = $since->format('Y-m-d H:i:s');

        $avg = HelpdeskTicket::query()
            ->when($businessUnitId !== null, fn ($q) => $q->where('helpdesk_tickets.business_unit_id', $businessUnitId))
            ->leftJoinSub($this->firstStaffCommentSubquery(), 'fr', function ($join) {
                $join->on('helpdesk_tickets.id', '=', 'fr.ticket_id');
            })
            ->whereRaw('COALESCE(helpdesk_tickets.first_response_at, fr.staff_first_at, helpdesk_tickets.resolved_at) IS NOT NULL')
            ->whereRaw('COALESCE(helpdesk_tickets.first_response_at, fr.staff_first_at, helpdesk_tickets.resolved_at) >= ?', [$sinceStr])
            ->selectRaw(
                'AVG(TIMESTAMPDIFF(MINUTE, helpdesk_tickets.created_at, COALESCE(helpdesk_tickets.first_response_at, fr.staff_first_at, helpdesk_tickets.resolved_at))) AS avg_min'
            )
            ->value('avg_min');

        return $avg !== null ? (int) round((float) $avg) : null;
    }

    /**
     * @return Builder<HelpdeskTicketComment>
     */
    private function firstStaffCommentSubquery(): Builder
    {
        return HelpdeskTicketComment::query()
            ->join('helpdesk_profiles as hp', 'hp.user_id', '=', 'helpdesk_ticket_comments.user_id')
            ->where('helpdesk_ticket_comments.is_internal', false)
            ->where('hp.role', '!=', HelpdeskProfile::ROLE_USER)
            ->selectRaw('helpdesk_ticket_comments.ticket_id, MIN(helpdesk_ticket_comments.created_at) AS staff_first_at')
            ->groupBy('helpdesk_ticket_comments.ticket_id');
    }
}
