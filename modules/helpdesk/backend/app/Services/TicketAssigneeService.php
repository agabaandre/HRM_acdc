<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use Illuminate\Database\Eloquent\Builder;

class TicketAssigneeService
{
    /**
     * @param  list<int>  $userIds
     */
    public function sync(HelpdeskTicket $ticket, array $userIds, ?int $primaryUserId = null): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id) => $id > 0)));
        $primaryUserId = $primaryUserId ?? ($userIds[0] ?? null);

        if ($primaryUserId !== null && ! in_array($primaryUserId, $userIds, true)) {
            array_unshift($userIds, $primaryUserId);
            $userIds = array_values(array_unique($userIds));
        }

        $sync = [];
        foreach ($userIds as $userId) {
            $sync[$userId] = ['is_primary' => $primaryUserId !== null && $userId === $primaryUserId];
        }

        $ticket->assignees()->sync($sync);
    }

    /**
     * @return list<int>
     */
    public function assigneeUserIds(HelpdeskTicket $ticket): array
    {
        if ($ticket->relationLoaded('assignees')) {
            return $ticket->assignees
                ->sortByDesc(fn ($user) => (bool) ($user->pivot->is_primary ?? false))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $ticket->assignees()
            ->orderByDesc('helpdesk_ticket_assignees.is_primary')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function openWorkloadForUser(int $userId): int
    {
        return HelpdeskTicket::query()
            ->assignedToUser($userId)
            ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
            ->count();
    }

    /**
     * @param  Builder<HelpdeskTicket>  $query
     */
    public function scopeAssignedToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $w) use ($userId): void {
            $w->where('assigned_user_id', $userId)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('users.id', $userId));
        });
    }
}
