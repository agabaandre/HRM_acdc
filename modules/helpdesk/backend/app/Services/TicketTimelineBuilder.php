<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a chronological lifecycle timeline for ticket detail UI.
 */
class TicketTimelineBuilder
{
    /**
     * @return list<array{key: string, label: string, at: string|null, detail: string|null, actor: string|null, kind: string}>
     */
    public function build(HelpdeskTicket $ticket): array
    {
        $ticket->loadMissing(['histories.user', 'resolvedBy', 'resolutionSubmittedBy']);

        /** @var Collection<int, HelpdeskTicketHistory> $histories */
        $histories = $ticket->relationLoaded('histories')
            ? $ticket->histories->sortBy('id')->values()
            : $ticket->histories()->with('user')->orderBy('id')->get();

        $items = [];
        $seenKeys = [];

        $push = function (
            string $key,
            string $label,
            ?CarbonInterface $at,
            ?string $detail = null,
            ?string $actor = null,
            string $kind = 'event',
        ) use (&$items, &$seenKeys): void {
            if ($at === null) {
                return;
            }
            $dedupe = $key.'|'.$at->toIso8601String();
            if (isset($seenKeys[$dedupe])) {
                return;
            }
            $seenKeys[$dedupe] = true;
            $items[] = [
                'key' => $key,
                'label' => $label,
                'at' => $at->toIso8601String(),
                'detail' => $detail !== null && trim($detail) !== '' ? trim($detail) : null,
                'actor' => $actor !== null && trim($actor) !== '' ? trim($actor) : null,
                'kind' => $kind,
            ];
        };

        $push(
            'opened',
            'Opened',
            $ticket->created_at,
            'Ticket created',
            null,
            'milestone'
        );

        if ($ticket->first_response_at) {
            $push(
                'first_response',
                'First response',
                $ticket->first_response_at,
                'Agent first responded',
                null,
                'event'
            );
        }

        foreach ($histories as $history) {
            $at = $history->created_at;
            $actor = trim((string) ($history->user?->name ?? ''));
            $payload = is_array($history->payload) ? $history->payload : [];

            switch ((string) $history->event) {
                case 'ticket.created':
                    // Covered by Opened milestone.
                    break;

                case 'ticket.resolved':
                    $push(
                        'resolved',
                        'Resolved',
                        $at ?? $ticket->resolved_at,
                        'Resolution submitted',
                        $actor !== '' ? $actor : ($ticket->resolutionSubmittedBy?->name ?? $ticket->resolvedBy?->name),
                        'milestone'
                    );
                    break;

                case 'ticket.closed':
                    $detail = ! empty($payload['auto_closed'])
                        ? 'Closed automatically after the review period'
                        : (! empty($payload['requester_confirmed'])
                            ? 'Closed by requester confirmation'
                            : 'Ticket closed');
                    $push('closed', 'Closed', $at ?? $ticket->closed_at, $detail, $actor !== '' ? $actor : null, 'milestone');
                    break;

                case 'ticket.reopened':
                    $push('reopened', 'Reopened', $at, 'Ticket reopened for further work', $actor !== '' ? $actor : null, 'event');
                    break;

                case 'ticket.reassigned':
                    $toNames = $payload['to_user_names'] ?? null;
                    $to = is_array($toNames)
                        ? implode(', ', array_filter(array_map('strval', $toNames)))
                        : trim((string) ($payload['to_name'] ?? $payload['assignee_name'] ?? ''));
                    $group = trim((string) ($payload['to_group_name'] ?? ''));
                    $parts = [];
                    if ($to !== '') {
                        $parts[] = 'Assigned to '.$to;
                    } elseif ($group !== '') {
                        $parts[] = 'Assigned to group '.$group;
                    } else {
                        $parts[] = 'Ticket reassigned';
                    }
                    $reason = trim((string) ($payload['reason'] ?? ''));
                    if ($reason !== '') {
                        $parts[] = $reason;
                    }
                    $push('reassigned', 'Reassigned', $at, implode(' · ', $parts), $actor !== '' ? $actor : null, 'event');
                    break;

                case 'ticket.updated':
                    $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
                    if (isset($changes['status'])) {
                        $statusChange = $changes['status'];
                        $toStatus = is_array($statusChange)
                            ? (string) ($statusChange['to'] ?? $statusChange[1] ?? '')
                            : (string) $statusChange;
                        if ($toStatus !== '' && ! in_array($toStatus, ['resolved', 'closed'], true)) {
                            $push(
                                'status_'.$toStatus,
                                $this->statusLabel($toStatus),
                                $at,
                                'Status updated',
                                $actor !== '' ? $actor : null,
                                'event'
                            );
                        }
                    }
                    break;
            }
        }

        // Fallback milestones when history rows are missing (e.g. public token close).
        if ($ticket->resolved_at) {
            $push(
                'resolved',
                'Resolved',
                $ticket->resolved_at,
                'Resolution submitted',
                $ticket->resolutionSubmittedBy?->name ?? $ticket->resolvedBy?->name,
                'milestone'
            );
        }

        if ($ticket->resolution_confirmed_at) {
            $push(
                'confirmed',
                'Confirmed',
                $ticket->resolution_confirmed_at,
                'Requester confirmed the resolution',
                null,
                'event'
            );
        }

        if ($ticket->closed_at) {
            $push('closed', 'Closed', $ticket->closed_at, 'Ticket closed', null, 'milestone');
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['key'], (string) $b['key']);
        });

        return array_values($items);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'awaiting_requester_confirmation' => 'Pending closure',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
