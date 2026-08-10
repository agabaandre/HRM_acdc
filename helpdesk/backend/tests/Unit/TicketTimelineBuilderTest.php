<?php

namespace Tests\Unit;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketHistory;
use App\Services\TicketTimelineBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class TicketTimelineBuilderTest extends TestCase
{
    public function test_builds_opened_resolved_closed_milestones_from_timestamps(): void
    {
        $ticket = new HelpdeskTicket;
        $ticket->forceFill([
            'created_at' => Carbon::parse('2026-08-01 09:00:00'),
            'resolved_at' => Carbon::parse('2026-08-02 14:30:00'),
            'closed_at' => Carbon::parse('2026-08-03 11:00:00'),
        ]);
        $ticket->syncOriginal();
        $ticket->setRelation('histories', collect());
        $ticket->setRelation('resolvedBy', null);
        $ticket->setRelation('resolutionSubmittedBy', null);

        $timeline = (new TicketTimelineBuilder)->build($ticket);
        $keys = array_column($timeline, 'key');

        $this->assertSame(['opened', 'resolved', 'closed'], $keys);
        $this->assertSame('Opened', $timeline[0]['label']);
        $this->assertSame('Resolved', $timeline[1]['label']);
        $this->assertSame('Closed', $timeline[2]['label']);
    }

    public function test_includes_middle_status_and_reassign_events_from_history(): void
    {
        $ticket = new HelpdeskTicket;
        $ticket->forceFill([
            'created_at' => Carbon::parse('2026-08-01 09:00:00'),
            'resolved_at' => Carbon::parse('2026-08-02 16:00:00'),
            'closed_at' => Carbon::parse('2026-08-03 10:00:00'),
        ]);
        $ticket->syncOriginal();

        $histories = collect([
            (new HelpdeskTicketHistory)->forceFill([
                'id' => 1,
                'event' => 'ticket.updated',
                'payload' => ['changes' => ['status' => 'in_progress']],
                'created_at' => Carbon::parse('2026-08-01 10:00:00'),
            ]),
            (new HelpdeskTicketHistory)->forceFill([
                'id' => 2,
                'event' => 'ticket.reassigned',
                'payload' => [
                    'to_user_names' => ['Ada Agent'],
                    'reason' => 'Coverage',
                ],
                'created_at' => Carbon::parse('2026-08-01 11:00:00'),
            ]),
            (new HelpdeskTicketHistory)->forceFill([
                'id' => 3,
                'event' => 'ticket.resolved',
                'payload' => [],
                'created_at' => Carbon::parse('2026-08-02 16:00:00'),
            ]),
            (new HelpdeskTicketHistory)->forceFill([
                'id' => 4,
                'event' => 'ticket.closed',
                'payload' => ['requester_confirmed' => true],
                'created_at' => Carbon::parse('2026-08-03 10:00:00'),
            ]),
        ]);

        foreach ($histories as $h) {
            $h->setRelation('user', null);
        }

        $ticket->setRelation('histories', $histories);
        $ticket->setRelation('resolvedBy', null);
        $ticket->setRelation('resolutionSubmittedBy', null);

        $timeline = (new TicketTimelineBuilder)->build($ticket);
        $keys = array_column($timeline, 'key');

        $this->assertSame(['opened', 'status_in_progress', 'reassigned', 'resolved', 'closed'], $keys);
        $this->assertStringContainsString('Ada Agent', (string) $timeline[2]['detail']);
        $this->assertStringContainsString('Coverage', (string) $timeline[2]['detail']);
        $this->assertStringContainsString('requester', strtolower((string) $timeline[4]['detail']));
    }
}
