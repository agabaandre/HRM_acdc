<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Jobs\AssignEndUserTicket;
use App\Jobs\CategorizeTicketWithAi;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskItAsset;
use App\Models\HelpdeskInformationSystem;
use App\Models\HelpdeskProfile;
use App\Support\InformationSystemStatus;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use App\Services\AgentCategoryRoutingService;
use App\Services\HtmlSanitizer;
use App\Services\RequesterTicketFollowUpService;
use App\Services\StaffDirectoryLookupService;
use App\Services\TicketAssigneeService;
use App\Services\TicketHistoryLogger;
use App\Services\TicketNumberGenerator;
use App\Services\TicketPriorityResolver;
use App\Services\TicketReadCache;
use App\Services\TicketSubjectGenerator;
use App\Support\StaffPhotoUrl;
use App\Support\TicketCreateIdempotency;
use App\Mail\TicketClosedMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
                ->with(['category', 'assignee.helpdeskProfile', 'assignees', 'assignedGroup', 'attachments']);

            if ($profile && $profile->role === HelpdeskProfile::ROLE_USER && $profile->staff_id) {
                $uid = $user->id;
                $sid = (int) $profile->staff_id;
                $q->where(function ($w) use ($sid, $uid) {
                    $w->where('requester_staff_id', $sid)
                        ->orWhere('created_by_user_id', $uid);
                });
            }

            if ($request->boolean('assigned_to_me')) {
                $q->assignedToUser((int) $user->id);
            }

            $statusIn = trim((string) $request->query('status_in', ''));
            if ($statusIn !== '') {
                $statuses = array_values(array_filter(array_map('trim', explode(',', $statusIn))));
                if ($statuses !== []) {
                    $q->whereIn('status', $statuses);
                }
            }

            $this->applyTicketSearch($q, $qTerm);
            $this->applyTicketSort($q, $request);

            $tickets = $q->paginate(min((int) $request->get('per_page', 20), 100));

            return TicketResource::collection($tickets)->response()->getData(true);
        });

        return response()->json($payload);
    }

    public function store(
        StoreTicketRequest $request,
        TicketNumberGenerator $numbers,
        TicketSubjectGenerator $subjects,
        StaffDirectoryLookupService $directoryLookup,
        TicketPriorityResolver $priorityResolver,
        AgentCategoryRoutingService $routing,
    ): JsonResponse {
        $user = $request->user();
        $profile = $user->helpdeskProfile;
        if (! $profile || ! $profile->staff_id) {
            abort(422, 'Helpdesk profile must include staff_id to create tickets.');
        }

        $idempotencyKey = TicketCreateIdempotency::normalizeClientKey($request->header('Idempotency-Key'));
        if ($idempotencyKey !== null) {
            $existingId = TicketCreateIdempotency::findTicketId((int) $user->id, $idempotencyKey);
            if ($existingId !== null) {
                $existing = HelpdeskTicket::query()
                    ->with(['category', 'assignee.helpdeskProfile', 'attachments'])
                    ->where('id', $existingId)
                    ->where('created_by_user_id', $user->id)
                    ->first();
                if ($existing) {
                    return (new TicketResource($existing))
                        ->response()
                        ->setStatusCode(200);
                }
            }
        }

        $businessUnit = HelpdeskBusinessUnit::query()->findOrFail((int) $request->validated('business_unit_id'));
        if (! $routing->businessUnitHasRoutableAgents((int) $businessUnit->id)) {
            abort(422, 'This business unit has no agents configured to handle tickets. Choose another unit or ask an administrator to assign agents.');
        }

        $categoryId = $request->filled('category_id') ? (int) $request->validated('category_id') : null;
        $category = $categoryId
            ? HelpdeskCategory::query()->findOrFail($categoryId)
            : null;
        if ($category !== null) {
            $covered = $routing->categoryIdsCoveredByEligibleAgents();
            if (! in_array((int) $category->id, $covered, true)) {
                abort(422, 'No agents are configured for this issue category. Choose another category or ask an administrator to assign agents.');
            }
        }
        $description = HtmlSanitizer::sanitize($request->validated('description'));
        if ($description === null) {
            abort(422, 'A description is required. Add text or images in the editor.');
        }

        $isAnonymous = $request->boolean('is_anonymous') && $businessUnit->allows_anonymous;

        $isEndUser = $profile->role === HelpdeskProfile::ROLE_USER;
        if ($isAnonymous) {
            $requesterStaffId = null;
            $requesterName = 'Anonymous';
            $requesterEmail = null;
            $agentLogged = false;
            $ticketDirectorateId = null;
            $ticketDivisionId = null;
            $requesterDutyStation = null;
        } elseif ($isEndUser) {
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
            $requesterDutyStation = $directoryLookup->dutyStationForStaffId((int) $requesterStaffId);
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
            $requesterDutyStation = $directoryLookup->dutyStationForStaffId($requesterStaffId);
        }

        $subjectName = $isAnonymous ? 'Anonymous' : $requesterName;
        if ($category) {
            $subject = $subjects->generate($category, $subjectName, $description);
            $priority = $priorityResolver->resolveForCreate($category, (int) ($requesterStaffId ?? 0));
        } else {
            $subject = $subjects->generateForBusinessUnit($businessUnit->name, $subjectName, $description);
            $priority = 'medium';
        }

        $ticketMeta = [];
        if ($requesterDutyStation !== null && $requesterDutyStation !== '') {
            $ticketMeta['requester_duty_station'] = $requesterDutyStation;
        }
        if ($isAnonymous) {
            $ticketMeta['anonymous'] = true;
        }

        $ticket = HelpdeskTicket::query()->create([
            'created_by_user_id' => $user->id,
            'ticket_number' => $numbers->next(),
            'category_id' => $category?->id,
            'business_unit_id' => $businessUnit->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
            'source' => $request->validated('source', 'web'),
            'agent_logged_for_requester' => $agentLogged,
            'requester_staff_id' => $requesterStaffId,
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'is_anonymous' => $isAnonymous,
            'directorate_id' => $ticketDirectorateId,
            'division_id' => $ticketDivisionId,
            'meta' => $ticketMeta !== [] ? $ticketMeta : null,
        ]);

        if ($category) {
            AssignEndUserTicket::dispatchAfterResponse($ticket->id, $requesterDutyStation);
        } else {
            // Business unit only — AI categorizes then assigns (or admins on failure).
            try {
                CategorizeTicketWithAi::dispatchAfterResponse($ticket->id, $requesterDutyStation);
            } catch (Throwable $e) {
                Log::warning('helpdesk.ticket.ai_categorize_dispatch_failed', [
                    'ticket_id' => $ticket->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($idempotencyKey !== null) {
            TicketCreateIdempotency::remember((int) $user->id, $idempotencyKey, (int) $ticket->id);
        }

        return (new TicketResource($ticket->load(['category', 'businessUnit', 'assignee.helpdeskProfile', 'attachments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, HelpdeskTicket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load([
            'category',
            'businessUnit',
            'linkedItAsset.category',
            'linkedItAsset.brandRelation',
            'linkedInformationSystem',
            'assignee.helpdeskProfile',
            'assignees',
            'attachments',
        ]));
    }

    /**
     * Assets that may be linked when resolving this ticket (requester's assigned inventory).
     * Search matches tag, serial, name, brand, model, location, assignee.
     */
    public function linkableAssets(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('submitResolution', $ticket);

        $ticket->loadMissing(['businessUnit']);
        if (! $ticket->businessUnit?->allows_asset_link_on_resolve) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false]]);
        }

        $staffId = (int) ($ticket->requester_staff_id ?? 0);
        $query = HelpdeskItAsset::query()
            ->with(['category:id,name', 'brandRelation:id,name'])
            ->orderBy('asset_tag');

        if ($staffId > 0) {
            $query->where('assigned_staff_id', $staffId);
        } else {
            // Anonymous / missing requester — nothing assignable by staff id.
            return response()->json(['data' => [], 'meta' => ['enabled' => true, 'requester_staff_id' => null]]);
        }

        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('asset_tag', 'like', $q)
                    ->orWhere('name', 'like', $q)
                    ->orWhere('brand', 'like', $q)
                    ->orWhere('model', 'like', $q)
                    ->orWhere('serial_number', 'like', $q)
                    ->orWhere('assigned_name', 'like', $q)
                    ->orWhere('location', 'like', $q);
            });
        }

        $rows = $query->limit(50)->get()->map(fn (HelpdeskItAsset $a) => [
            'id' => $a->id,
            'asset_tag' => $a->asset_tag,
            'name' => $a->name,
            'brand' => $a->brandRelation?->name ?? $a->brand,
            'model' => $a->model,
            'serial_number' => $a->serial_number,
            'status' => $a->status,
            'location' => $a->location,
            'category' => $a->category ? ['id' => $a->category->id, 'name' => $a->category->name] : null,
            'label' => trim(implode(' · ', array_filter([
                $a->asset_tag,
                $a->name,
                $a->brandRelation?->name ?? $a->brand,
                $a->model,
                $a->serial_number ? 'S/N '.$a->serial_number : null,
            ]))),
        ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'enabled' => true,
                'requester_staff_id' => $staffId,
            ],
        ]);
    }

    /**
     * Information systems that may be linked when resolving this ticket.
     */
    public function linkableInformationSystems(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('submitResolution', $ticket);

        $ticket->loadMissing(['businessUnit']);
        if (! $ticket->businessUnit?->allows_information_system_link_on_resolve) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false]]);
        }

        $query = HelpdeskInformationSystem::query()
            ->where('status', '!=', InformationSystemStatus::DECOMMISSIONED)
            ->orderBy('name');

        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('description', 'like', $q)
                    ->orWhere('domain', 'like', $q)
                    ->orWhere('version', 'like', $q);
            });
        }

        $rows = $query->limit(50)->get()->map(fn (HelpdeskInformationSystem $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'status' => $s->status,
            'version' => $s->version,
            'label' => trim(implode(' · ', array_filter([
                $s->name,
                $s->version ? 'v'.$s->version : null,
                $s->status,
            ]))),
        ]);

        return response()->json([
            'data' => $rows,
            'meta' => ['enabled' => true],
        ]);
    }

    public function update(
        UpdateTicketRequest $request,
        HelpdeskTicket $ticket,
        TicketSubjectGenerator $subjects,
    ): TicketResource {
        $this->authorize('update', $ticket);

        $data = $request->validated();
        $profile = $request->user()->helpdeskProfile;

        if ($profile && $profile->role === HelpdeskProfile::ROLE_USER) {
            unset(
                $data['status'],
                $data['assigned_user_id'],
                $data['category_id'],
                $data['business_unit_id'],
                $data['priority']
            );
        } elseif (array_key_exists('priority', $data) && ! $profile?->canReassignTickets()) {
            unset($data['priority']);
        }

        if (array_key_exists('category_id', $data) || array_key_exists('business_unit_id', $data)) {
            $this->authorize('changeCategory', $ticket);
        }

        if (array_key_exists('category_id', $data) && ! empty($data['category_id'])) {
            $category = HelpdeskCategory::query()->find((int) $data['category_id']);
            if ($category) {
                $requestedBu = isset($data['business_unit_id']) ? (int) $data['business_unit_id'] : null;
                if ($requestedBu && (int) $category->business_unit_id !== $requestedBu) {
                    abort(422, 'Choose a category that belongs to the selected business unit.');
                }
                if (! isset($data['business_unit_id']) && $category->business_unit_id) {
                    $data['business_unit_id'] = (int) $category->business_unit_id;
                }
            }
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::sanitize($data['description']);
        }

        $oldCategoryId = (int) ($ticket->category_id ?? 0);
        $oldBusinessUnitId = (int) ($ticket->business_unit_id ?? 0);
        $classificationTouched = array_key_exists('category_id', $data)
            || array_key_exists('business_unit_id', $data);

        $ticket->fill($data);

        $classificationChanged = $classificationTouched
            && (
                (int) ($ticket->category_id ?? 0) !== $oldCategoryId
                || (int) ($ticket->business_unit_id ?? 0) !== $oldBusinessUnitId
            );

        if ($classificationChanged && ! array_key_exists('subject', $data)) {
            $ticket->unsetRelation('category');
            $ticket->unsetRelation('businessUnit');
            $ticket->subject = $subjects->regenerateForTicket($ticket);
        }

        $ticket->save();

        return new TicketResource($ticket->fresh()->load(['category', 'businessUnit', 'assignee.helpdeskProfile', 'assignees', 'attachments']));
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
     * Users with reassign permission may pick any assignable agent or group,
     * not only those routed to the ticket category.
     */
    public function eligibleAgents(Request $request, HelpdeskTicket $ticket, TicketAssigneeService $assignees): JsonResponse
    {
        $this->authorize('view', $ticket);
        $this->ensureReassignAllowed($request, $ticket);

        $ticket->loadMissing(['assignees', 'category']);

        $assigneeUserIds = $assignees->assigneeUserIds($ticket);
        if ($assigneeUserIds === [] && $ticket->assigned_user_id) {
            $assigneeUserIds = [(int) $ticket->assigned_user_id];
        }

        $agents = User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->assignableAsTicketAssignee())
            ->with(['helpdeskProfile:id,user_id,duty_station,role', 'helpdeskSupportGroups:id,name'])
            ->orderBy('name')
            ->get();

        $groups = HelpdeskSupportGroup::query()
            ->where('is_active', true)
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'current' => [
                    'assignee_user_ids' => $assigneeUserIds,
                    'assigned_group_id' => $ticket->assigned_group_id,
                    'priority' => $ticket->priority,
                    'business_unit_id' => $ticket->business_unit_id,
                    'category_id' => $ticket->category_id,
                ],
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
                    'open_workload' => $assignees->openWorkloadForUser((int) $u->id),
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
    public function reassign(
        Request $request,
        HelpdeskTicket $ticket,
        TicketHistoryLogger $logger,
        TicketAssigneeService $assignees,
        TicketSubjectGenerator $subjects,
    ): JsonResponse {
        $this->authorize('view', $ticket);
        $this->ensureReassignAllowed($request, $ticket);

        if (! in_array($ticket->status, self::REASSIGNABLE_STATUSES, true)) {
            abort(422, 'Only open / pending / in-progress tickets can be reassigned.');
        }

        $validated = $request->validate([
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_user_ids' => ['nullable', 'array'],
            'assignee_user_ids.*' => ['integer', 'exists:users,id'],
            'assignee_group_id' => ['nullable', 'integer', 'exists:helpdesk_support_groups,id'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'business_unit_id' => ['nullable', 'integer', 'exists:helpdesk_business_units,id'],
            'category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $newAssigneeIds = collect($validated['assignee_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($newAssigneeIds === [] && ! empty($validated['assignee_user_id'])) {
            $newAssigneeIds = [(int) $validated['assignee_user_id']];
        }

        $newGroupId = isset($validated['assignee_group_id']) ? (int) $validated['assignee_group_id'] : null;
        if ($newAssigneeIds === [] && ! $newGroupId) {
            abort(422, 'Select at least one agent or a support group.');
        }

        $newPriority = $validated['priority'] ?? null;
        $newCategoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $newBusinessUnitId = isset($validated['business_unit_id']) ? (int) $validated['business_unit_id'] : null;

        if ($newCategoryId) {
            $categoryForBu = HelpdeskCategory::query()->find($newCategoryId);
            if (! $categoryForBu) {
                abort(422, 'Selected category was not found.');
            }
            if ($newBusinessUnitId && (int) $categoryForBu->business_unit_id !== $newBusinessUnitId) {
                abort(422, 'Choose a category that belongs to the selected business unit.');
            }
            if (! $newBusinessUnitId && $categoryForBu->business_unit_id) {
                $newBusinessUnitId = (int) $categoryForBu->business_unit_id;
            }
        }

        $oldAssigneeIds = $assignees->assigneeUserIds($ticket);
        if ($oldAssigneeIds === [] && $ticket->assigned_user_id) {
            $oldAssigneeIds = [(int) $ticket->assigned_user_id];
        }
        $oldGroupId = (int) ($ticket->assigned_group_id ?? 0);
        $oldPriority = $ticket->priority;
        $oldCategoryId = (int) ($ticket->category_id ?? 0);
        $oldBusinessUnitId = (int) ($ticket->business_unit_id ?? 0);

        $assigneesChanged = $oldAssigneeIds !== $newAssigneeIds;
        $groupChanged = $newGroupId !== ($oldGroupId > 0 ? $oldGroupId : null);
        $priorityChanged = $newPriority !== null && $newPriority !== $oldPriority;
        $categoryChanged = $newCategoryId !== null && $newCategoryId !== $oldCategoryId;
        $businessUnitChanged = $newBusinessUnitId !== null && $newBusinessUnitId !== $oldBusinessUnitId;

        if (! $assigneesChanged && ! $groupChanged && ! $priorityChanged && ! $categoryChanged && ! $businessUnitChanged) {
            abort(422, 'Nothing changed — pick different agents, group, priority, business unit, or category.');
        }

        $newPrimaryId = $newAssigneeIds[0] ?? null;
        foreach ($newAssigneeIds as $assigneeId) {
            $candidate = User::query()->with('helpdeskProfile')->findOrFail($assigneeId);
            $profile = $candidate->helpdeskProfile;
            if (! $profile || ! $profile->canBeAssignedTickets()) {
                abort(422, 'Selected user is not a Helpdesk agent.');
            }
        }

        $newGroup = null;
        if ($newGroupId) {
            $newGroup = HelpdeskSupportGroup::query()->where('is_active', true)->findOrFail($newGroupId);
        }

        $oldAssignee = $ticket->assigned_user_id
            ? User::query()->find((int) $ticket->assigned_user_id)
            : null;
        $oldGroup = $oldGroupId > 0
            ? HelpdeskSupportGroup::query()->find($oldGroupId)
            : null;
        $oldCategory = HelpdeskCategory::query()->find($oldCategoryId);
        $oldBusinessUnit = $oldBusinessUnitId > 0
            ? HelpdeskBusinessUnit::query()->find($oldBusinessUnitId)
            : null;

        $newAssignee = $newPrimaryId ? User::query()->find($newPrimaryId) : null;
        $newCategory = $newCategoryId ? HelpdeskCategory::query()->find($newCategoryId) : null;
        $newBusinessUnit = $newBusinessUnitId ? HelpdeskBusinessUnit::query()->find($newBusinessUnitId) : null;

        $reason = trim((string) $validated['reason']);

        $ticket->assigned_user_id = $newPrimaryId;
        $ticket->assigned_group_id = $newGroupId;
        if ($newPriority !== null) {
            $ticket->priority = $newPriority;
        }
        if ($newCategoryId !== null) {
            $ticket->category_id = $newCategoryId;
        }
        if ($newBusinessUnitId !== null) {
            $ticket->business_unit_id = $newBusinessUnitId;
        }
        if ($categoryChanged || $businessUnitChanged) {
            $ticket->unsetRelation('category');
            $ticket->unsetRelation('businessUnit');
            $ticket->subject = $subjects->regenerateForTicket($ticket);
        }
        $ticket->save();

        $assignees->sync($ticket, $newAssigneeIds, $newPrimaryId);

        app(\App\Services\TicketAssignmentNotifier::class)->notifyAddedAssignees(
            $ticket->fresh(['category']),
            $oldAssigneeIds,
            $newAssigneeIds,
        );

        $oldAssigneeNames = User::query()->whereIn('id', $oldAssigneeIds)->orderBy('name')->pluck('name')->all();
        $newAssigneeNames = User::query()->whereIn('id', $newAssigneeIds)->orderBy('name')->pluck('name')->all();

        $logger->log($ticket, 'ticket.reassigned', $request->user()->id, [
            'from_user_ids' => $oldAssigneeIds !== [] ? $oldAssigneeIds : null,
            'from_user_names' => $oldAssigneeNames !== [] ? $oldAssigneeNames : null,
            'from_group_id' => $oldGroupId > 0 ? $oldGroupId : null,
            'from_group_name' => $oldGroup?->name,
            'from_priority' => $priorityChanged ? $oldPriority : null,
            'from_business_unit_id' => $businessUnitChanged ? $oldBusinessUnitId : null,
            'from_business_unit_name' => $businessUnitChanged ? $oldBusinessUnit?->name : null,
            'from_category_id' => $categoryChanged ? $oldCategoryId : null,
            'from_category_name' => $categoryChanged ? $oldCategory?->name : null,
            'to_user_ids' => $newAssigneeIds !== [] ? $newAssigneeIds : null,
            'to_user_names' => $newAssigneeNames !== [] ? $newAssigneeNames : null,
            'to_group_id' => $newGroupId,
            'to_group_name' => $newGroup?->name,
            'to_priority' => $priorityChanged ? $newPriority : null,
            'to_business_unit_id' => $businessUnitChanged ? $newBusinessUnitId : null,
            'to_business_unit_name' => $businessUnitChanged ? $newBusinessUnit?->name : null,
            'to_category_id' => $categoryChanged ? $newCategoryId : null,
            'to_category_name' => $categoryChanged ? $newCategory?->name : null,
            'reason' => $reason,
        ]);

        $changeLines = [];
        if ($assigneesChanged) {
            $fromAgents = $oldAssigneeNames !== [] ? implode(', ', $oldAssigneeNames) : 'Unassigned';
            $toAgents = $newAssigneeNames !== [] ? implode(', ', $newAssigneeNames) : 'Unassigned';
            $changeLines[] = "Agents: {$fromAgents} → {$toAgents}";
        }
        if ($groupChanged) {
            $fromGroup = $oldGroup?->name ?? 'None';
            $toGroup = $newGroup?->name ?? 'None';
            $changeLines[] = "Group: {$fromGroup} → {$toGroup}";
        }
        if ($priorityChanged) {
            $changeLines[] = "Priority: {$oldPriority} → {$newPriority}";
        }
        if ($businessUnitChanged) {
            $fromBu = $oldBusinessUnit?->name ?? 'Unknown';
            $toBu = $newBusinessUnit?->name ?? 'Unknown';
            $changeLines[] = "Business unit: {$fromBu} → {$toBu}";
        }
        if ($categoryChanged) {
            $fromCat = $oldCategory?->name ?? 'Unknown';
            $toCat = $newCategory?->name ?? 'Unknown';
            $changeLines[] = "Category: {$fromCat} → {$toCat}";
        }

        HelpdeskTicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_staff_id' => $request->user()->helpdeskProfile?->staff_id,
            'is_internal' => true,
            'body' => sprintf(
                "Assignment updated.\n\n%s\n\nReason: %s",
                implode("\n", $changeLines),
                $reason
            ),
        ]);

        return response()->json([
            'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'assignees', 'assignedGroup', 'attachments'])))->resolve(),
            'meta' => [
                'from_user_ids' => $oldAssigneeIds,
                'from_group_id' => $oldGroupId > 0 ? $oldGroupId : null,
                'to_user_ids' => $newAssigneeIds,
                'to_group_id' => $newGroupId,
                'to_priority' => $newPriority,
                'to_category_id' => $newCategoryId,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Requester closes a resolved ticket after confirming the solution (ITIL closure).
     */
    public function confirmClose(
        Request $request,
        HelpdeskTicket $ticket,
        TicketHistoryLogger $logger,
    ): JsonResponse {
        $this->authorize('confirmClose', $ticket);

        $ticket->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'resolution_confirmed_at' => now(),
            'resolution_confirm_token' => null,
        ])->save();

        $logger->log($ticket, 'ticket.closed', $request->user()->id, [
            'requester_confirmed' => true,
        ]);

        return response()->json([
            'message' => 'Thank you — this ticket is now closed.',
            'data' => (new TicketResource($ticket->fresh()->load(['category', 'assignee.helpdeskProfile', 'assignees', 'attachments'])))->resolve(),
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
                ->orWhereHas('assignees', function ($a) use ($like) {
                    $a->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    private function applyTicketSort(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $sortBy = (string) $request->query('sort_by', 'id');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $columns = [
            'id' => 'helpdesk_tickets.id',
            'ticket_number' => 'helpdesk_tickets.ticket_number',
            'subject' => 'helpdesk_tickets.subject',
            'requester_name' => 'helpdesk_tickets.requester_name',
            'status' => 'helpdesk_tickets.status',
            'priority' => 'helpdesk_tickets.priority',
            'created_at' => 'helpdesk_tickets.created_at',
        ];

        if ($sortBy === 'assignee_name') {
            $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'helpdesk_tickets.assigned_user_id')
                    ->limit(1),
                $sortDir
            );

            return;
        }

        if (! array_key_exists($sortBy, $columns)) {
            $query->orderByDesc('helpdesk_tickets.id');

            return;
        }

        $query->orderBy($columns[$sortBy], $sortDir);
    }
}
