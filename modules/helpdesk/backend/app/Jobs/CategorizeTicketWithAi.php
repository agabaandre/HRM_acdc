<?php

namespace App\Jobs;

use App\Models\HelpdeskTicket;
use App\Services\TicketAiCategorizationService;
use App\Services\TicketAssigneeService;
use App\Services\TicketAssignmentService;
use App\Services\TicketPriorityResolver;
use App\Services\TicketSubjectGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async AI (or heuristic) categorization for tickets created with business unit only.
 * On success: set category and run normal category routing.
 * On failure: round-robin assign to helpdesk admins.
 */
class CategorizeTicketWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $ticketId,
        public ?string $requesterDutyStation = null,
    ) {
        $this->onQueue('helpdesk-ai');
    }

    public function handle(
        TicketAiCategorizationService $categorizer,
        TicketAssignmentService $assignment,
        TicketAssigneeService $assignees,
        TicketSubjectGenerator $subjects,
        TicketPriorityResolver $priorityResolver,
    ): void {
        $ticket = HelpdeskTicket::query()->with(['category', 'businessUnit'])->find($this->ticketId);
        if (! $ticket || $ticket->category_id) {
            return;
        }

        $category = null;
        try {
            $category = $categorizer->categorize($ticket);
        } catch (\Throwable $e) {
            Log::warning('helpdesk.ai_categorize.exception', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);
        }

        if ($category) {
            $ticket->category_id = $category->id;
            if (! $ticket->business_unit_id && $category->business_unit_id) {
                $ticket->business_unit_id = $category->business_unit_id;
            }

            $requesterLabel = $ticket->is_anonymous
                ? 'Anonymous'
                : (string) ($ticket->requester_name ?: 'Requester');
            $ticket->subject = $subjects->generate($category, $requesterLabel, $ticket->description);
            $ticket->priority = $priorityResolver->resolveForCreate(
                $category,
                $ticket->requester_staff_id ? (int) $ticket->requester_staff_id : 0
            );
            $ticket->save();

            if (! $ticket->assigned_user_id && ! $ticket->assigned_group_id) {
                $result = $assignment->assignAgentOrSupervisorFallback($ticket, $this->requesterDutyStation);
                if ($result['user_id'] || $result['group_id']) {
                    $ticket->assigned_user_id = $result['user_id'];
                    $ticket->assigned_group_id = $result['group_id'];
                    $ticket->save();
                    if ($result['user_id']) {
                        $assignees->sync($ticket, [(int) $result['user_id']], (int) $result['user_id']);
                    }
                    if ($result['fallback']) {
                        $meta = is_array($ticket->meta) ? $ticket->meta : [];
                        $meta['assigned_via_supervisor_fallback'] = true;
                        $ticket->meta = $meta;
                        $ticket->save();
                    }
                }
            }

            return;
        }

        // Categorization failed — supervisors (then admins) by open workload.
        if ($ticket->assigned_user_id || $ticket->assigned_group_id) {
            return;
        }

        $result = $assignment->assignSupervisorRoundRobin($ticket);
        if (! $result['user_id']) {
            Log::warning('helpdesk.ai_categorize.no_supervisor_assignee', ['ticket_id' => $ticket->id]);

            return;
        }

        $ticket->assigned_user_id = $result['user_id'];
        $ticket->save();
        $assignees->sync($ticket, [(int) $result['user_id']], (int) $result['user_id']);

        $meta = is_array($ticket->meta) ? $ticket->meta : [];
        $meta['ai_categorization_failed'] = true;
        $meta['assigned_via_supervisor_fallback'] = true;
        $ticket->meta = $meta;
        $ticket->save();
    }
}
