<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Jobs\ScanTicketForAiSignals;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;
use App\Services\StaffDirectoryLookupService;
use App\Services\TicketAssignmentService;
use App\Services\TicketHistoryLogger;
use App\Services\TicketNumberGenerator;
use App\Services\TicketSubjectGenerator;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $user = $request->user();
        $profile = $user->helpdeskProfile;
        $q = HelpdeskTicket::query()
            ->with(['category', 'assignee.helpdeskProfile', 'attachments'])
            ->orderByDesc('id');

        if ($profile && $profile->role === HelpdeskProfile::ROLE_USER && $profile->staff_id) {
            $uid = $user->id;
            $sid = (int) $profile->staff_id;
            $q->where(function ($w) use ($sid, $uid) {
                $w->where('requester_staff_id', $sid)
                    ->orWhere('created_by_user_id', $uid);
            });
        }

        $tickets = $q->paginate(min((int) $request->get('per_page', 20), 100));

        return TicketResource::collection($tickets);
    }

    public function store(
        StoreTicketRequest $request,
        TicketNumberGenerator $numbers,
        TicketSubjectGenerator $subjects,
        TicketAssignmentService $assignment,
        StaffDirectoryLookupService $directoryLookup,
    ): JsonResponse {
        $user = $request->user();
        $profile = $user->helpdeskProfile;
        if (! $profile || ! $profile->staff_id) {
            abort(422, 'Helpdesk profile must include staff_id to create tickets.');
        }

        $category = HelpdeskCategory::query()->findOrFail((int) $request->validated('category_id'));
        $description = $request->validated('description');

        $isEndUser = $profile->role === HelpdeskProfile::ROLE_USER;
        if ($isEndUser) {
            $selfStaffId = (int) $profile->staff_id;
            $forOther = $request->filled('requester_staff_id')
                && (int) $request->input('requester_staff_id') !== $selfStaffId;

            if ($forOther) {
                $requesterStaffId = (int) $request->input('requester_staff_id');
                $resolved = $directoryLookup->resolveByStaffId($requesterStaffId);
                if ($resolved === null) {
                    abort(422, 'Requester not found in the Staff directory. Run directory sync in Settings → Jobs or pick another staff member.');
                }
                $requesterEmail = $resolved['work_email'];
                if ($requesterEmail === '') {
                    abort(422, 'Selected requester has no work email in the Staff directory.');
                }
                $requesterName = $resolved['name'];
                $agentLogged = true;
                $ticketDirectorateId = $resolved['directorate_id'] ?? $profile->directorate_id;
                $ticketDivisionId = $resolved['division_id'] ?? $profile->division_id;
            } else {
                $requesterStaffId = $selfStaffId;
                $resolvedSelf = $directoryLookup->resolveByStaffId($requesterStaffId);
                if ($resolvedSelf !== null && $resolvedSelf['work_email'] !== '') {
                    $requesterName = $resolvedSelf['name'];
                    $requesterEmail = $resolvedSelf['work_email'];
                } else {
                    $requesterName = $user->name;
                    $requesterEmail = (string) $user->email;
                }
                $agentLogged = false;
                $ticketDirectorateId = $profile->directorate_id;
                $ticketDivisionId = $profile->division_id;
            }
        } else {
            $requesterStaffId = (int) $request->validated('requester_staff_id');
            $resolved = $directoryLookup->resolveByStaffId($requesterStaffId);
            if ($resolved === null) {
                abort(422, 'Requester not found in the Staff directory. Run directory sync in Settings → Jobs or pick another staff member.');
            }
            $requesterEmail = $resolved['work_email'];
            if ($requesterEmail === '') {
                abort(422, 'Selected requester has no work email in the Staff directory.');
            }
            $requesterName = $resolved['name'];
            $agentLogged = true;
            $ticketDirectorateId = $resolved['directorate_id'] ?? $profile->directorate_id;
            $ticketDivisionId = $resolved['division_id'] ?? $profile->division_id;
        }

        $subjectName = $requesterName;
        $subject = $subjects->generate($category, $subjectName, $description);

        $priority = $isEndUser
            ? 'medium'
            : ($request->validated('priority') ?? 'medium');

        $ticket = HelpdeskTicket::query()->create([
            'created_by_user_id' => $user->id,
            'ticket_number' => $numbers->next(),
            'category_id' => (int) $request->validated('category_id'),
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
            'source' => $request->validated('source', 'web'),
            'agent_logged_for_requester' => $agentLogged,
            'requester_staff_id' => $requesterStaffId,
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'directorate_id' => $ticketDirectorateId,
            'division_id' => $ticketDivisionId,
        ]);

        if ($isEndUser) {
            $station = $directoryLookup->dutyStationForStaffId($requesterStaffId);
            $agentId = $assignment->assignAgent($ticket, $station);
            if ($agentId) {
                $ticket->assigned_user_id = $agentId;
                $ticket->save();
            }
        } else {
            $ticket->assigned_user_id = $user->id;
            $ticket->save();
        }

        ScanTicketForAiSignals::dispatch($ticket->id);

        return (new TicketResource($ticket->load(['category', 'assignee.helpdeskProfile', 'attachments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, HelpdeskTicket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load(['category', 'assignee.helpdeskProfile', 'attachments']));
    }

    public function update(UpdateTicketRequest $request, HelpdeskTicket $ticket): TicketResource
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();
        $profile = $request->user()->helpdeskProfile;

        if ($profile && $profile->role === HelpdeskProfile::ROLE_USER) {
            unset($data['status'], $data['assigned_user_id'], $data['category_id'], $data['priority']);
        }

        $ticket->fill($data);
        $ticket->save();

        return new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'attachments']));
    }

    public function destroy(Request $request, HelpdeskTicket $ticket): Response
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return response()->noContent();
    }

    /**
     * Statuses that may be re-assigned to a new agent. Resolved / closed /
     * awaiting-confirm tickets are excluded to prevent disrupting workflows
     * the requester has already been notified about.
     */
    private const REASSIGNABLE_STATUSES = ['open', 'pending', 'in_progress'];

    /**
     * List candidate agents the current user may reassign this ticket to.
     * Returns agents that handle the ticket's category, excluding the
     * current assignee.
     */
    public function eligibleAgents(Request $request, HelpdeskTicket $ticket, TicketAssignmentService $assignment): JsonResponse
    {
        $this->authorize('view', $ticket);
        $this->ensureReassignAllowed($request, $ticket);

        $eligibleIds = $assignment->eligibleAgentUserIds($ticket);
        $currentAssignee = (int) ($ticket->assigned_user_id ?? 0);
        $filtered = array_values(array_filter($eligibleIds, fn (int $id) => $id !== $currentAssignee));

        if ($filtered === []) {
            return response()->json(['data' => []]);
        }

        $agents = User::query()
            ->whereIn('id', $filtered)
            ->with(['helpdeskProfile:id,user_id,duty_station,role'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $agents->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => StaffPhotoUrl::forUser($u),
                'duty_station' => $u->helpdeskProfile?->duty_station,
                'open_workload' => HelpdeskTicket::query()
                    ->where('assigned_user_id', $u->id)
                    ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                    ->count(),
            ])->values(),
        ]);
    }

    /**
     * Reassign an open ticket to another agent. Reason is required and
     * recorded in ticket history + as an internal comment.
     */
    public function reassign(Request $request, HelpdeskTicket $ticket, TicketHistoryLogger $logger): JsonResponse
    {
        $this->authorize('view', $ticket);
        $this->ensureReassignAllowed($request, $ticket);

        if (! in_array($ticket->status, self::REASSIGNABLE_STATUSES, true)) {
            abort(422, 'Only open / pending / in-progress tickets can be reassigned.');
        }

        $validated = $request->validate([
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $newAssigneeId = (int) $validated['assignee_user_id'];
        $oldAssigneeId = (int) ($ticket->assigned_user_id ?? 0);
        if ($newAssigneeId === $oldAssigneeId) {
            abort(422, 'That ticket is already assigned to this agent — pick a different one.');
        }

        $newAssignee = User::query()->with('helpdeskProfile')->findOrFail($newAssigneeId);
        $newProfile = $newAssignee->helpdeskProfile;
        if (! $newProfile || ! in_array($newProfile->role, [
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_ADMIN,
        ], true)) {
            abort(422, 'Selected user is not a Helpdesk agent.');
        }

        $oldAssignee = $oldAssigneeId > 0
            ? User::query()->find($oldAssigneeId)
            : null;

        $reason = trim((string) $validated['reason']);

        $ticket->assigned_user_id = $newAssigneeId;
        $ticket->save();

        // Structured history entry — surfaces on the ticket activity timeline
        // with from/to/reason metadata for audit and analytics.
        $logger->log($ticket, 'ticket.reassigned', $request->user()->id, [
            'from_user_id' => $oldAssigneeId > 0 ? $oldAssigneeId : null,
            'from_user_name' => $oldAssignee?->name,
            'to_user_id' => $newAssigneeId,
            'to_user_name' => $newAssignee->name,
            'reason' => $reason,
        ]);

        // Internal comment so the new assignee + other agents see the reason
        // inline without digging into the history payload.
        $fromLabel = $oldAssignee?->name ?? 'Unassigned';
        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_staff_id' => $request->user()->helpdeskProfile?->staff_id,
            'is_internal' => true,
            'body' => sprintf(
                "Reassigned from %s to %s.\n\nReason: %s",
                $fromLabel,
                $newAssignee->name,
                $reason
            ),
        ]);

        return response()->json([
            'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'attachments'])))->resolve(),
            'meta' => [
                'from_user_id' => $oldAssigneeId > 0 ? $oldAssigneeId : null,
                'to_user_id' => $newAssigneeId,
                'reason' => $reason,
            ],
        ]);
    }

    private function ensureReassignAllowed(Request $request, HelpdeskTicket $ticket): void
    {
        $profile = $request->user()?->helpdeskProfile;
        abort_unless(
            $profile && $profile->canReassignTickets(),
            403,
            'You need the “Can reassign tickets” permission to do this.'
        );
    }
}
