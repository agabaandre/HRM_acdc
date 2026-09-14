<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Exports\SoftwareRequestsExport;
use App\Http\Controllers\Concerns\DownloadsPdfReports;
use App\Http\Controllers\Controller;
use App\Models\HelpdeskSoftwareRequest;
use App\Models\HelpdeskSoftwareRequestApproval;
use App\Models\HelpdeskSoftwareRequestTeamMember;
use App\Services\DivisionHeadResolver;
use App\Services\HelpdeskPdfReportService;
use App\Services\HtmlSanitizer;
use App\Services\SoftwareRequestNotifyService;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SoftwareRequestController extends Controller
{
    use AuthorizesHelpdeskTools;
    use DownloadsPdfReports;

    public function summary(Request $request): JsonResponse
    {
        $this->ensureSoftwareRequestManage($request);

        $rows = HelpdeskSoftwareRequest::query()->get(['id', 'status', 'decision', 'priority', 'budget_estimate']);
        $byStatus = [];
        $byPriority = [];
        $byDecision = [];
        foreach ($rows as $row) {
            $st = (string) ($row->status ?: 'unknown');
            $pr = (string) ($row->priority ?: 'unset');
            $dec = (string) ($row->decision ?: 'pending');
            $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
            $byPriority[$pr] = ($byPriority[$pr] ?? 0) + 1;
            $byDecision[$dec] = ($byDecision[$dec] ?? 0) + 1;
        }

        return response()->json([
            'data' => [
                'total' => $rows->count(),
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
                'by_decision' => $byDecision,
                'budget_total' => round((float) $rows->sum('budget_estimate'), 2),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->ensureSoftwareRequestManage($request);

        $rows = HelpdeskSoftwareRequest::query()->orderByDesc('id')->limit(5000)->get();

        return Excel::download(
            new SoftwareRequestsExport($rows),
            'software-requests-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function exportPdf(Request $request, HelpdeskPdfReportService $pdf): Response
    {
        $this->ensureSoftwareRequestManage($request);

        $requests = HelpdeskSoftwareRequest::query()->orderByDesc('id')->limit(2000)->get();
        $rows = $requests->map(fn (HelpdeskSoftwareRequest $r) => [
            $r->request_number,
            $r->request_title,
            $r->status,
            $r->decision,
            $r->priority,
            $r->requester_name,
            $r->division_name,
            $r->desired_timeline,
            $r->budget_estimate,
            optional($r->received_at)?->format('Y-m-d'),
        ])->all();

        $summaryLines = [
            'Requests: '.$requests->count(),
            'Budget total: '.round((float) $requests->sum('budget_estimate'), 2),
        ];

        return $this->pdfTableDownload(
            $request,
            $pdf,
            'Software requests',
            ['Request #', 'Title', 'Status', 'Decision', 'Priority', 'Requester', 'Division', 'Timeline', 'Budget', 'Received'],
            $rows,
            'software-requests-'.now()->format('Y-m-d').'.pdf',
            $summaryLines,
        );
    }

    public function index(Request $request, DivisionHeadResolver $heads): JsonResponse
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        $canManage = $profile && ($profile->canManageSoftwareRequests() || $profile->canApproveSoftwareRequests());
        $staffId = (int) ($profile?->staff_id ?? 0);

        $query = HelpdeskSoftwareRequest::query()
            ->with(['teamMembers', 'approvals'])
            ->orderByDesc('updated_at');

        if (! $canManage) {
            $this->ensureSoftwareRequestSubmit($request);
            $query->where(function ($q) use ($user, $staffId, $heads) {
                $q->where('requester_user_id', $user?->id);
                if ($staffId > 0) {
                    $q->orWhere(function ($hodQ) use ($staffId) {
                        $hodQ->where('status', 'pending_hod')
                            ->where('hod_staff_id', $staffId);
                    });
                    $pendingDivisionIds = HelpdeskSoftwareRequest::query()
                        ->where('status', 'pending_hod')
                        ->whereNotNull('division_id')
                        ->pluck('division_id')
                        ->unique()
                        ->filter()
                        ->values();
                    $myDivisions = [];
                    foreach ($pendingDivisionIds as $divId) {
                        if ($heads->effectiveHeadStaffIdForDivision((int) $divId) === $staffId) {
                            $myDivisions[] = (int) $divId;
                        }
                    }
                    if ($myDivisions !== []) {
                        $q->orWhere(function ($divQ) use ($myDivisions) {
                            $divQ->where('status', 'pending_hod')
                                ->whereIn('division_id', $myDivisions);
                        });
                    }
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->input('division_id'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'like', $q)
                    ->orWhere('request_title', 'like', $q)
                    ->orWhere('requester_name', 'like', $q)
                    ->orWhere('division_name', 'like', $q)
                    ->orWhere('directorate_name', 'like', $q)
                    ->orWhere('department', 'like', $q);
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

    public function store(Request $request, StaffDirectoryLookupService $directory, SoftwareRequestNotifyService $notifier, DivisionHeadResolver $heads): JsonResponse
    {
        $this->ensureSoftwareRequestSubmit($request);
        $user = $request->user();
        $profile = $user->helpdeskProfile;

        $validated = $this->validatedPayload($request);
        $org = $this->resolveOrgFields($user->id, $profile?->staff_id, $directory, $profile?->division_id, $profile?->directorate_id);
        $submit = $request->boolean('submit');

        $hodStaffId = ! empty($org['division_id'])
            ? $heads->effectiveHeadStaffIdForDivision((int) $org['division_id'])
            : null;
        $hodName = null;
        if ($hodStaffId) {
            $resolved = $directory->resolveByStaffId($hodStaffId);
            $hodName = $resolved['name'] ?? ('Staff '.$hodStaffId);
        }

        $row = HelpdeskSoftwareRequest::query()->create(array_merge($validated, $org, [
            'request_number' => HelpdeskSoftwareRequest::generateRequestNumber(),
            'requester_user_id' => $user->id,
            'requester_name' => $validated['requester_name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'department' => $org['division_name'] ?? ($validated['department'] ?? null),
            'status' => $submit ? 'pending_hod' : 'draft',
            'received_at' => $submit ? now() : null,
            'hod_staff_id' => $hodStaffId,
            'hod_name' => $hodName,
        ]));

        if ($submit) {
            $notifier->notifyNewSubmission($row);
        }

        return response()->json(['data' => $row->fresh(['teamMembers', 'approvals'])], 201);
    }

    public function update(Request $request, HelpdeskSoftwareRequest $softwareRequest, SoftwareRequestNotifyService $notifier): JsonResponse
    {
        $this->assertCanEdit($request, $softwareRequest);

        $validated = $this->validatedPayload($request, true);
        $submit = $request->boolean('submit');
        $wasDraft = in_array($softwareRequest->status, ['draft', 'returned'], true);

        if ($submit && $wasDraft) {
            $validated['status'] = 'pending_hod';
            $validated['received_at'] = $softwareRequest->received_at ?? now();
        }

        $softwareRequest->fill($validated);
        $softwareRequest->save();

        if ($submit && $wasDraft) {
            $notifier->notifyNewSubmission($softwareRequest->fresh());
        }

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    public function hodApprove(
        Request $request,
        HelpdeskSoftwareRequest $softwareRequest,
        DivisionHeadResolver $heads,
    ): JsonResponse {
        $this->assertIsHod($request, $softwareRequest, $heads);
        abort_unless(
            $softwareRequest->status === 'pending_hod',
            422,
            'Only requests pending Head of Division approval can be approved here.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $softwareRequest->status = 'hod_approved';
        $softwareRequest->hod_decided_at = now();
        $softwareRequest->hod_decided_by_user_id = $user->id;
        $softwareRequest->hod_decision_notes = $notes;
        $softwareRequest->save();

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    public function hodReject(
        Request $request,
        HelpdeskSoftwareRequest $softwareRequest,
        DivisionHeadResolver $heads,
    ): JsonResponse {
        $this->assertIsHod($request, $softwareRequest, $heads);
        abort_unless(
            $softwareRequest->status === 'pending_hod',
            422,
            'Only requests pending Head of Division approval can be rejected here.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $softwareRequest->status = 'hod_rejected';
        $softwareRequest->hod_decided_at = now();
        $softwareRequest->hod_decided_by_user_id = $user->id;
        $softwareRequest->hod_decision_notes = $notes;
        $softwareRequest->decision = 'rejected';
        $softwareRequest->save();

        return response()->json(['data' => $softwareRequest->fresh(['teamMembers', 'approvals'])]);
    }

    public function approve(Request $request, HelpdeskSoftwareRequest $softwareRequest): JsonResponse
    {
        $this->ensureSoftwareRequestManage($request);
        $this->assertHodGatePassed($softwareRequest);
        $user = $request->user();

        $validated = $request->validate([
            'approval_role' => ['required', 'string', Rule::in(['team_lead', 'business_analyst', 'project_lead', 'review_board'])],
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

        if (in_array($validated['approval_role'], ['team_lead', 'review_board'], true)) {
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
        $this->assertHodGatePassed($softwareRequest);

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
            'problem_statement' => ['nullable', 'string', 'max:65000'],
            'proposed_solution' => ['nullable', 'string', 'max:65000'],
            'business_justification' => ['nullable', 'string', 'max:65000'],
            'affected_stakeholders' => ['nullable', 'string'],
            'mandate_alignment' => ['nullable', 'string', 'max:191'],
            'priority' => ['nullable', 'string', Rule::in(['critical', 'high', 'medium', 'low'])],
            'desired_timeline' => ['nullable', 'string', 'max:191'],
            'budget_estimate' => ['nullable', 'numeric', 'min:0'],
            'existing_alternatives' => ['nullable', 'string'],
            'additional_comments' => ['nullable', 'string'],
        ];

        $validated = $request->validate($rules);

        foreach (['problem_statement', 'proposed_solution', 'business_justification'] as $richKey) {
            if (! array_key_exists($richKey, $validated) || ! is_string($validated[$richKey])) {
                continue;
            }
            $sanitized = HtmlSanitizer::sanitize($validated[$richKey]);
            $validated[$richKey] = $sanitized;
        }

        return $validated;
    }

    /**
     * @return array{division_id:?int,directorate_id:?int,division_name:?string,directorate_name:?string}
     */
    private function resolveOrgFields(
        int $userId,
        ?int $staffId,
        StaffDirectoryLookupService $directory,
        ?int $profileDivisionId,
        ?int $profileDirectorateId,
    ): array {
        $divisionId = $profileDivisionId ? (int) $profileDivisionId : null;
        $directorateId = $profileDirectorateId ? (int) $profileDirectorateId : null;
        $divisionName = null;
        $directorateName = null;

        if ($staffId && $staffId > 0) {
            $resolved = $directory->resolveByStaffId((int) $staffId);
            if ($resolved !== null) {
                // Profile (SSO) wins when set; directory fills gaps only.
                $divisionId = $divisionId ?: ($resolved['division_id'] ?? null);
                $directorateId = $directorateId ?: ($resolved['directorate_id'] ?? null);
            }
        }

        [$divisionName, $directorateName, $directorateId] = $this->lookupOrgNames($divisionId, $directorateId);

        return [
            'division_id' => $divisionId,
            'directorate_id' => $directorateId,
            'division_name' => $divisionName,
            'directorate_name' => $directorateName,
        ];
    }

    /**
     * @return array{0:?string,1:?string,2:?int}
     */
    private function lookupOrgNames(?int $divisionId, ?int $directorateId): array
    {
        $bundle = Cache::get('helpdesk_reference_bundle_v1');
        $divisions = is_array($bundle['divisions'] ?? null) ? $bundle['divisions'] : [];
        $directorates = is_array($bundle['directorates'] ?? null) ? $bundle['directorates'] : [];

        $divisionName = null;
        $directorateName = null;

        if ($divisionId) {
            foreach ($divisions as $d) {
                if (! is_array($d)) {
                    continue;
                }
                if ((int) ($d['id'] ?? 0) === $divisionId) {
                    $divisionName = (string) ($d['name'] ?? '');
                    if (! $directorateId && isset($d['directorate_id'])) {
                        $directorateId = (int) $d['directorate_id'] ?: null;
                    }
                    break;
                }
            }
        }

        if ($directorateId) {
            foreach ($directorates as $d) {
                if (! is_array($d)) {
                    continue;
                }
                if ((int) ($d['id'] ?? 0) === $directorateId) {
                    $directorateName = (string) ($d['name'] ?? '');
                    break;
                }
            }
        }

        return [
            $divisionName !== '' ? $divisionName : null,
            $directorateName !== '' ? $directorateName : null,
            $directorateId,
        ];
    }

    private function assertCanView(Request $request, HelpdeskSoftwareRequest $row, ?DivisionHeadResolver $heads = null): void
    {
        $profile = $request->user()?->helpdeskProfile;
        if ($profile && ($profile->canManageSoftwareRequests() || $profile->canApproveSoftwareRequests())) {
            return;
        }
        if ((int) $row->requester_user_id === (int) $request->user()?->id) {
            return;
        }
        $heads ??= app(DivisionHeadResolver::class);
        if ($this->userIsHodFor($request, $row, $heads)) {
            return;
        }
        abort(403);
    }

    private function assertCanEdit(Request $request, HelpdeskSoftwareRequest $row): void
    {
        if (in_array($row->status, ['draft', 'returned', 'hod_rejected'], true) && (int) $row->requester_user_id === (int) $request->user()?->id) {
            return;
        }
        $profile = $request->user()?->helpdeskProfile;
        abort_unless($profile && $profile->canManageSoftwareRequests(), 403);
    }

    private function assertHodGatePassed(HelpdeskSoftwareRequest $row): void
    {
        $allowed = ['hod_approved', 'approved', 'deferred', 'team_formed', 'submitted'];
        // Legacy "submitted" rows (pre-HoD gate) remain processable.
        abort_unless(
            in_array($row->status, $allowed, true),
            403,
            'Software requests can only be processed after Head of Division approval.',
        );
    }

    private function assertIsHod(Request $request, HelpdeskSoftwareRequest $row, DivisionHeadResolver $heads): void
    {
        abort_unless($this->userIsHodFor($request, $row, $heads), 403, 'Only the Head of Division can approve this request.');
    }

    private function userIsHodFor(Request $request, HelpdeskSoftwareRequest $row, DivisionHeadResolver $heads): bool
    {
        $staffId = (int) ($request->user()?->helpdeskProfile?->staff_id ?? 0);
        if ($staffId <= 0) {
            return false;
        }
        if ($row->hod_staff_id && (int) $row->hod_staff_id === $staffId) {
            return true;
        }
        $divId = (int) ($row->division_id ?? 0);
        if ($divId <= 0) {
            return false;
        }

        return $heads->effectiveHeadStaffIdForDivision($divId) === $staffId;
    }
}
