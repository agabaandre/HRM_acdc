<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Jobs\ScanTicketForAiSignals;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\RequesterTicketFollowUpService;
use App\Services\StaffDirectoryLookupService;
use App\Services\TicketAssignmentService;
use App\Services\TicketHistoryLogger;
use App\Services\TicketNumberGenerator;
use App\Services\TicketReadCache;
use App\Services\TicketSubjectGenerator;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $user = $request->user();
        $cacheKey = TicketReadCache::key('tickets', 'index', (int) $user->id, $request->query());

        $payload = TicketReadCache::remember($cacheKey, function () use ($request, $user) {
            $profile = $user->helpdeskProfile;
            $qTerm = trim((string) $request->query('q', ''));
            $q = HelpdeskTicket::query()
                ->with(['category', 'assignee.helpdeskProfile', 'assignedGroup', 'attachments'])
                ->orderByDesc('id');

            if ($profile && $profile->role === HelpdeskProfile::ROLE_USER && $profile->staff_id) {
                $uid = $user->id;
                $sid = (int) $profile->staff_id;
                $q->where(function ($w) use ($sid, $uid) {
                    $w->where('requester_staff_id', $sid)
                        ->orWhere('created_by_user_id', $uid);
                });
            }

            $this->applyTicketSearch($q, $qTerm);

            $tickets = $q->paginate(min((int) $request->get('per_page', 20), 100));

            return TicketResource::collection($tickets)->response()->getData(true);
        });

        return response()->json($payload);
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
        $description = HtmlSanitizer::sanitize($request->validated('description'));
        if ($description === null) {
            abort(422, 'A description is required. Add text or images in the editor.');
        }

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
            $assignmentResult = $assignment->assignAgent($ticket, $station);
            if ($assignmentResult['user_id'] || $assignmentResult['group_id']) {
                $ticket->assigned_user_id = $assignmentResult['user_id'];
                $ticket->assigned_group_id = $assignmentResult['group_id'];
                $ticket->save();
            }
        } else {
            $ticket->assigned_user_id = $user->id;
            $ticket->save();
        }

        try {
            ScanTicketForAiSignals::dispatchAfterResponse($ticket->id);
        } catch (Throwable $e) {
            Log::warning('helpdesk.ticket.ai_scan_dispatch_failed', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);
        }

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

        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::sanitize($data['description']);
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

        $groups = HelpdeskSupportGroup::query()
            ->where('is_active', true)
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (HelpdeskSupportGroup $group) use ($ticket, $assignment) {
                $eligible = $assignment->eligibleGroupIds($ticket);

                return in_array((int) $group->id, $eligible, true);
            })
            ->values();

        $agents = $filtered === []
            ? collect()
            : User::query()
                ->whereIn('id', $filtered)
                ->with(['helpdeskProfile:id,user_id,duty_station,role', 'helpdeskSupportGroups:id,name'])
                ->orderBy('name')
                ->get();

        return response()->json([
            'data' => [
                'agents' => $agents->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar_url' => StaffPhotoUrl::forUser($u),
                    'duty_station' => $u->helpdeskProfile?->duty_station,
                    'support_groups' => $u->helpdeskSupportGroups->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                    ])->values(),
                    'open_workload' => HelpdeskTicket::query()
                        ->where('assigned_user_id', $u->id)
                        ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                        ->count(),
                ])->values(),
                'groups' => $groups->map(fn (HelpdeskSupportGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'members_count' => (int) $g->members_count,
                    'open_workload' => HelpdeskTicket::query()
                        ->where('assigned_group_id', $g->id)
                        ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                        ->count(),
                ])->values(),
            ],
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
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_group_id' => ['nullable', 'integer', 'exists:helpdesk_support_groups,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        if (empty($validated['assignee_user_id']) && empty($validated['assignee_group_id'])) {
            abort(422, 'Select an agent, a support group, or both.');
        }

        $newAssigneeId = isset($validated['assignee_user_id']) ? (int) $validated['assignee_user_id'] : null;
        $newGroupId = isset($validated['assignee_group_id']) ? (int) $validated['assignee_group_id'] : null;
        $oldAssigneeId = (int) ($ticket->assigned_user_id ?? 0);
        $oldGroupId = (int) ($ticket->assigned_group_id ?? 0);

        if ($newAssigneeId && $newAssigneeId === $oldAssigneeId && $newGroupId === $oldGroupId) {
            abort(422, 'That ticket already has this assignment — pick a different agent or group.');
        }

        $newAssignee = null;
        if ($newAssigneeId) {
            $newAssignee = User::query()->with('helpdeskProfile')->findOrFail($newAssigneeId);
            $newProfile = $newAssignee->helpdeskProfile;
            if (! $newProfile || ! in_array($newProfile->role, [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ], true)) {
                abort(422, 'Selected user is not a Helpdesk agent.');
            }
        }

        $newGroup = null;
        if ($newGroupId) {
            $newGroup = HelpdeskSupportGroup::query()->where('is_active', true)->findOrFail($newGroupId);
        }

        $oldAssignee = $oldAssigneeId > 0
            ? User::query()->find($oldAssigneeId)
            : null;
        $oldGroup = $oldGroupId > 0
            ? HelpdeskSupportGroup::query()->find($oldGroupId)
            : null;

        $reason = trim((string) $validated['reason']);

        $ticket->assigned_user_id = $newAssigneeId;
        $ticket->assigned_group_id = $newGroupId;
        $ticket->save();

        $logger->log($ticket, 'ticket.reassigned', $request->user()->id, [
            'from_user_id' => $oldAssigneeId > 0 ? $oldAssigneeId : null,
            'from_user_name' => $oldAssignee?->name,
            'from_group_id' => $oldGroupId > 0 ? $oldGroupId : null,
            'from_group_name' => $oldGroup?->name,
            'to_user_id' => $newAssigneeId,
            'to_user_name' => $newAssignee?->name,
            'to_group_id' => $newGroupId,
            'to_group_name' => $newGroup?->name,
            'reason' => $reason,
        ]);

        $fromParts = array_filter([
            $oldAssignee?->name,
            $oldGroup?->name ? 'group '.$oldGroup->name : null,
        ]);
        $toParts = array_filter([
            $newAssignee?->name,
            $newGroup?->name ? 'group '.$newGroup->name : null,
        ]);
        $fromLabel = $fromParts !== [] ? implode(' / ', $fromParts) : 'Unassigned';
        $toLabel = $toParts !== [] ? implode(' / ', $toParts) : 'Unassigned';
        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_staff_id' => $request->user()->helpdeskProfile?->staff_id,
            'is_internal' => true,
            'body' => sprintf(
                "Reassigned from %s to %s.\n\nReason: %s",
                $fromLabel,
                $toLabel,
                $reason
            ),
        ]);

        return response()->json([
            'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'assignedGroup', 'attachments'])))->resolve(),
            'meta' => [
                'from_user_id' => $oldAssigneeId > 0 ? $oldAssigneeId : null,
                'from_group_id' => $oldGroupId > 0 ? $oldGroupId : null,
                'to_user_id' => $newAssigneeId,
                'to_group_id' => $newGroupId,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Requester reopens a closed ticket when the resolution did not fix the issue.
     * When requester follow-up is enabled, a comment body is required so the assignee
     * receives one email containing both the comment and the reopen alert.
     */
    public function reopen(
        Request $request,
        HelpdeskTicket $ticket,
        RequesterTicketFollowUpService $followUp,
    ): JsonResponse {
        $this->authorize('reopen', $ticket);

        $user = $request->user();
        $profile = $user->helpdeskProfile;
        abort_unless($profile, 403);

        if (HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled()) {
            $validated = $request->validate([
                'body' => ['required', 'string', 'max:65535'],
            ]);

            $result = $followUp->commentAndMaybeReopen(
                $ticket,
                $user,
                $profile,
                $validated['body'],
                true,
            );

            return response()->json([
                'message' => 'Ticket reopened and your comment was sent to the assigned agent.',
                'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'attachments'])))->resolve(),
                'meta' => ['ticket_reopened' => $result['ticket_reopened']],
            ]);
        }

        $previousStatus = $ticket->status;
        $followUp->reopenTicket($ticket, $user, (string) $previousStatus, 'requester_reopen');

        return response()->json([
            'message' => 'Ticket reopened. Add a comment below with any extra details for the support team.',
            'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'attachments'])))->resolve(),
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

    private function applyTicketSearch(\Illuminate\Database\Eloquent\Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $query->where(function ($w) use ($like) {
            $w->where('ticket_number', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('requester_name', 'like', $like)
                ->orWhere('requester_email', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('priority', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('assignee', function ($a) use ($like) {
                    $a->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }
}
