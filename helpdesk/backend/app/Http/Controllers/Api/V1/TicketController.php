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
use App\Services\StaffDirectoryLookupService;
use App\Services\TicketAssignmentService;
use App\Services\TicketNumberGenerator;
use App\Services\TicketSubjectGenerator;
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
        $q = HelpdeskTicket::query()->with(['category', 'assignee', 'attachments'])->orderByDesc('id');

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

        return (new TicketResource($ticket->load(['category', 'assignee', 'attachments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, HelpdeskTicket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load(['category', 'assignee', 'attachments']));
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

        return new TicketResource($ticket->fresh()->load(['category', 'assignee', 'attachments']));
    }

    public function destroy(Request $request, HelpdeskTicket $ticket): Response
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return response()->noContent();
    }
}
