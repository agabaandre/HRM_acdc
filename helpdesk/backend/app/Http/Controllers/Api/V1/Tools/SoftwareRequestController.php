<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSoftwareRequest;
use App\Models\HelpdeskSoftwareRequestApproval;
use App\Models\HelpdeskSoftwareRequestTeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SoftwareRequestController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        $canManage = $profile && ($profile->canManageSoftwareRequests() || $profile->canApproveSoftwareRequests() || $profile->isHelpdeskAdmin());

        $query = HelpdeskSoftwareRequest::query()
            ->with(['teamMembers', 'approvals'])
            ->orderByDesc('updated_at');

        if (! $canManage) {
            $this->ensureSoftwareRequestSubmit($request);
            $query->where('requester_user_id', $user?->id);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'like', $q)
                    ->orWhere('request_title', 'like', $q)
                    ->orWhere('requester_name', 'like', $q);
            });
        }

        $rows = $query->paginate(min(100, max(10, (int) $request->input('per_page', 20))));

        return response()->json($rows);
    }

    public function show(Request $request, HelpdeskSoftwareRequest $softwareRequest): JsonResponse
    {
        $this->assertCanView($request, $softwareRequest);
        $softwareRequest->load(['teamMembers', 'approvals', 'requester:id,name,email']);

        return response()->json(['data' => $softwareRequest]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureSoftwareRequestSubmit($request);
        $user = $request->user();

        $validated = $this->validatedPayload($request);
        $submit = $request->boolean('submit');

        $row = HelpdeskSoftwareRequest::query()->create(array_merge($validated, [
            'request_number' => HelpdeskSoftwareRequest::generateRequestNumber(),
            'requester_user_id' => $user->id,
            'requester_name' => $validated['requester_name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'status' => $submit ? 'submitted' : 'draft',
            'received_at' => $submit ? now() : null,
        ]));

        return response()->json(['data' => $row->fresh(['teamMembers', 'approvals'])], 201);
    }

    public function update(Request $request, HelpdeskSoftwareRequest $softwareRequest): JsonResponse
    {
        $this->assertCanEdit($request, $softwareRequest);

        $validated = $this->validatedPayload($request, true);
        $submit = $request->boolean('submit');

        if ($submit && in_array($softwareRequest->status, ['draft', 'returned'], true)) {
            $validated['status'] = 'submitted';
            $validated['received_at'] = $softwareRequest->received_at ?? now();
        }

        $softwareRequest->fill($validated);
        $softwareRequest->save();

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    public function approve(Request $request, HelpdeskSoftwareRequest $softwareRequest): JsonResponse
    {
        $this->ensureSoftwareRequestManage($request);
        $user = $request->user();

        $validated = $request->validate([
            'approval_role' => ['required', 'string', Rule::in(['team_lead', 'business_analyst', 'project_lead'])],
            'decision' => ['required', 'string', Rule::in(['approved', 'deferred', 'rejected'])],
            'notes' => ['nullable', 'string'],
            'assigned_ba_staff_id' => ['nullable', 'integer', 'min:1'],
            'assigned_ba_name' => ['nullable', 'string', 'max:191'],
            'project_id' => ['nullable', 'string', 'max:64'],
        ]);

        HelpdeskSoftwareRequestApproval::query()->updateOrCreate(
            [
                'software_request_id' => $softwareRequest->id,
                'approval_role' => $validated['approval_role'],
            ],
            [
                'approver_user_id' => $user->id,
                'approver_name' => $user->name,
                'decision' => $validated['decision'],
                'notes' => $validated['notes'] ?? null,
                'decided_at' => now(),
            ]
        );

        if ($validated['approval_role'] === 'team_lead') {
            $softwareRequest->team_lead_review_at = now();
            $softwareRequest->team_lead_user_id = $user->id;
        }

        if (! empty($validated['assigned_ba_staff_id'])) {
            $softwareRequest->assigned_ba_staff_id = (int) $validated['assigned_ba_staff_id'];
            $softwareRequest->assigned_ba_name = $validated['assigned_ba_name'] ?? null;
        }
        if (! empty($validated['project_id'])) {
            $softwareRequest->project_id = $validated['project_id'];
        }

        $softwareRequest->decision = $validated['decision'];
        $softwareRequest->status = match ($validated['decision']) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'deferred',
        };
        $softwareRequest->save();

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    public function syncTeam(Request $request, HelpdeskSoftwareRequest $softwareRequest): JsonResponse
    {
        $this->ensureSoftwareRequestManage($request);

        $validated = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*.member_name' => ['required', 'string', 'max:191'],
            'members.*.member_email' => ['nullable', 'email', 'max:191'],
            'members.*.staff_id' => ['nullable', 'integer', 'min:1'],
            'members.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'members.*.role' => ['required', 'string', 'max:64'],
        ]);

        $softwareRequest->teamMembers()->delete();
        foreach ($validated['members'] as $member) {
            HelpdeskSoftwareRequestTeamMember::query()->create([
                'software_request_id' => $softwareRequest->id,
                'user_id' => $member['user_id'] ?? null,
                'staff_id' => $member['staff_id'] ?? null,
                'member_name' => $member['member_name'],
                'member_email' => $member['member_email'] ?? null,
                'role' => $member['role'],
            ]);
        }

        $softwareRequest->project_team_formed_at = now();
        if ($softwareRequest->status === 'approved') {
            $softwareRequest->status = 'team_formed';
        }
        $softwareRequest->save();

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'requester_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:191'],
            'department' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:64'],
            'request_title' => [$partial ? 'sometimes' : 'required', 'string', 'max:191'],
            'problem_statement' => ['nullable', 'string'],
            'proposed_solution' => ['nullable', 'string'],
            'business_justification' => ['nullable', 'string'],
            'affected_stakeholders' => ['nullable', 'string'],
            'mandate_alignment' => ['nullable', 'string', 'max:191'],
            'priority' => ['nullable', 'string', Rule::in(['critical', 'high', 'medium', 'low'])],
            'desired_timeline' => ['nullable', 'string', 'max:191'],
            'budget_estimate' => ['nullable', 'numeric', 'min:0'],
            'existing_alternatives' => ['nullable', 'string'],
            'additional_comments' => ['nullable', 'string'],
        ];

        return $request->validate($rules);
    }

    private function assertCanView(Request $request, HelpdeskSoftwareRequest $row): void
    {
        $profile = $request->user()?->helpdeskProfile;
        if ($profile && ($profile->canManageSoftwareRequests() || $profile->canApproveSoftwareRequests() || $profile->isHelpdeskAdmin())) {
            return;
        }
        abort_unless((int) $row->requester_user_id === (int) $request->user()?->id, 403);
    }

    private function assertCanEdit(Request $request, HelpdeskSoftwareRequest $row): void
    {
        if (in_array($row->status, ['draft', 'returned'], true) && (int) $row->requester_user_id === (int) $request->user()?->id) {
            return;
        }
        $profile = $request->user()?->helpdeskProfile;
        abort_unless($profile && $profile->canManageSoftwareRequests(), 403);
    }
}
