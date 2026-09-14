<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskHostingRequest;
use App\Services\DivisionHeadResolver;
use App\Services\HtmlSanitizer;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class HostingRequestController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function index(Request $request, DivisionHeadResolver $heads): JsonResponse
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        $canProcess = $profile && $profile->canProcessHostingRequests();
        $staffId = (int) ($profile?->staff_id ?? 0);

        $query = HelpdeskHostingRequest::query()->orderByDesc('updated_at');

        if (! $canProcess) {
            $query->where(function ($q) use ($user, $staffId, $heads) {
                $q->where('requester_user_id', $user?->id);
                if ($staffId > 0) {
                    $q->orWhere(function ($hodQ) use ($staffId) {
                        $hodQ->where('status', HelpdeskHostingRequest::STATUS_PENDING_HOD)
                            ->where('hod_staff_id', $staffId);
                    });
                    // Also match when hod_staff_id was snapshot differently but still effective head
                    $pendingDivisionIds = HelpdeskHostingRequest::query()
                        ->where('status', HelpdeskHostingRequest::STATUS_PENDING_HOD)
                        ->whereNotNull('requester_division_id')
                        ->pluck('requester_division_id')
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
                            $divQ->where('status', HelpdeskHostingRequest::STATUS_PENDING_HOD)
                                ->whereIn('requester_division_id', $myDivisions);
                        });
                    }
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'like', $q)
                    ->orWhere('title', 'like', $q)
                    ->orWhere('requester_name', 'like', $q)
                    ->orWhere('requester_division_name', 'like', $q);
            });
        }

        $rows = $query->paginate(min(100, max(10, (int) $request->input('per_page', 20))));

        return response()->json($rows);
    }

    public function show(Request $request, HelpdeskHostingRequest $hostingRequest, DivisionHeadResolver $heads): JsonResponse
    {
        $this->assertCanView($request, $hostingRequest, $heads);

        return response()->json(['data' => $hostingRequest->load('requester:id,name,email')]);
    }

    public function store(
        Request $request,
        StaffDirectoryLookupService $directory,
        DivisionHeadResolver $heads,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user?->helpdeskProfile, 403);

        $validated = $this->validatedPayload($request);
        $submit = $request->boolean('submit');
        $profile = $user->helpdeskProfile;

        $divisionId = $profile?->division_id ? (int) $profile->division_id : null;
        $divisionName = null;
        if ($profile?->staff_id) {
            $resolved = $directory->resolveByStaffId((int) $profile->staff_id);
            if ($resolved !== null) {
                $divisionId = $divisionId ?: ($resolved['division_id'] ?? null);
            }
        }
        if ($divisionId) {
            $div = $heads->divisionById($divisionId);
            $divisionName = $div['name'] ?? $this->divisionNameFromCache($divisionId);
        }

        $hodStaffId = $divisionId ? $heads->effectiveHeadStaffIdForDivision($divisionId) : null;
        $hodName = $hodStaffId ? $this->staffDisplayName($hodStaffId, $directory) : null;

        $row = HelpdeskHostingRequest::query()->create([
            ...$validated,
            'request_number' => HelpdeskHostingRequest::generateRequestNumber(),
            'status' => $submit ? HelpdeskHostingRequest::STATUS_PENDING_HOD : HelpdeskHostingRequest::STATUS_DRAFT,
            'requester_user_id' => $user->id,
            'requester_staff_id' => $profile?->staff_id,
            'requester_name' => $validated['requester_name'] ?? $user->name,
            'requester_division_id' => $divisionId,
            'requester_division_name' => $divisionName,
            'hod_staff_id' => $hodStaffId,
            'hod_name' => $hodName,
            'created_by_user_id' => $user->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskHostingRequest $hostingRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user
            && (int) $hostingRequest->requester_user_id === (int) $user->id
            && in_array($hostingRequest->status, [
                HelpdeskHostingRequest::STATUS_DRAFT,
                HelpdeskHostingRequest::STATUS_HOD_REJECTED,
            ], true),
            403,
        );

        $validated = $this->validatedPayload($request, true);
        $submit = $request->boolean('submit');
        $hostingRequest->fill($validated);
        if ($submit) {
            $hostingRequest->status = HelpdeskHostingRequest::STATUS_PENDING_HOD;
        }
        $hostingRequest->save();

        return response()->json(['data' => $hostingRequest->fresh()]);
    }

    public function hodApprove(
        Request $request,
        HelpdeskHostingRequest $hostingRequest,
        DivisionHeadResolver $heads,
    ): JsonResponse {
        $this->assertIsHod($request, $hostingRequest, $heads);
        abort_unless(
            $hostingRequest->status === HelpdeskHostingRequest::STATUS_PENDING_HOD,
            422,
            'Only pending HoD requests can be approved.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $hostingRequest->status = HelpdeskHostingRequest::STATUS_HOD_APPROVED;
        $hostingRequest->hod_decided_at = now();
        $hostingRequest->hod_decided_by_user_id = $user->id;
        $hostingRequest->hod_decision_notes = $notes;
        $hostingRequest->save();

        return response()->json(['data' => $hostingRequest->fresh()]);
    }

    public function hodReject(
        Request $request,
        HelpdeskHostingRequest $hostingRequest,
        DivisionHeadResolver $heads,
    ): JsonResponse {
        $this->assertIsHod($request, $hostingRequest, $heads);
        abort_unless(
            $hostingRequest->status === HelpdeskHostingRequest::STATUS_PENDING_HOD,
            422,
            'Only pending HoD requests can be rejected.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $hostingRequest->status = HelpdeskHostingRequest::STATUS_HOD_REJECTED;
        $hostingRequest->hod_decided_at = now();
        $hostingRequest->hod_decided_by_user_id = $user->id;
        $hostingRequest->hod_decision_notes = $notes;
        $hostingRequest->save();

        return response()->json(['data' => $hostingRequest->fresh()]);
    }

    public function process(Request $request, HelpdeskHostingRequest $hostingRequest): JsonResponse
    {
        $this->ensureHostingProcess($request);
        abort_unless(
            $hostingRequest->status === HelpdeskHostingRequest::STATUS_HOD_APPROVED,
            403,
            'Hosting requests can only be processed after Head of Division approval.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $hostingRequest->status = HelpdeskHostingRequest::STATUS_IN_PROGRESS;
        $hostingRequest->processed_by_user_id = $user->id;
        $hostingRequest->processed_at = now();
        $hostingRequest->process_notes = $notes;
        $hostingRequest->save();

        return response()->json(['data' => $hostingRequest->fresh()]);
    }

    public function complete(Request $request, HelpdeskHostingRequest $hostingRequest): JsonResponse
    {
        $this->ensureHostingProcess($request);
        abort_unless(
            $hostingRequest->status === HelpdeskHostingRequest::STATUS_IN_PROGRESS,
            422,
            'Only in-progress requests can be completed.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        if ($notes !== null) {
            $hostingRequest->process_notes = $notes;
        }
        $hostingRequest->status = HelpdeskHostingRequest::STATUS_COMPLETED;
        $hostingRequest->save();

        return response()->json(['data' => $hostingRequest->fresh()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $validated = $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:65000'],
            'category' => [$partial ? 'sometimes' : 'required', 'string', Rule::in([
                HelpdeskHostingRequest::CATEGORY_CLOUD,
                HelpdeskHostingRequest::CATEGORY_ON_PREMISES,
            ])],
            'cloud_provider' => ['nullable', 'string', 'max:191'],
            'environment_notes' => ['nullable', 'string', 'max:65000'],
            'requester_name' => ['nullable', 'string', 'max:191'],
            'on_behalf_of_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $category = $validated['category'] ?? $request->input('category');
        if ($category === HelpdeskHostingRequest::CATEGORY_CLOUD) {
            $provider = trim((string) ($validated['cloud_provider'] ?? ''));
            abort_unless($partial || $provider !== '', 422, 'Cloud provider is required for cloud hosting requests.');
        }

        if (isset($validated['description']) && is_string($validated['description'])) {
            $validated['description'] = HtmlSanitizer::sanitize($validated['description']);
        }
        if (isset($validated['environment_notes']) && is_string($validated['environment_notes'])) {
            $validated['environment_notes'] = HtmlSanitizer::sanitize($validated['environment_notes']);
        }

        return $validated;
    }

    private function assertCanView(Request $request, HelpdeskHostingRequest $row, DivisionHeadResolver $heads): void
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        if ($profile && $profile->canProcessHostingRequests()) {
            return;
        }
        if ($user && (int) $row->requester_user_id === (int) $user->id) {
            return;
        }
        if ($this->userIsHodFor($request, $row, $heads)) {
            return;
        }
        abort(403);
    }

    private function assertIsHod(Request $request, HelpdeskHostingRequest $row, DivisionHeadResolver $heads): void
    {
        abort_unless($this->userIsHodFor($request, $row, $heads), 403, 'Only the Head of Division can approve this request.');
    }

    private function userIsHodFor(Request $request, HelpdeskHostingRequest $row, DivisionHeadResolver $heads): bool
    {
        $staffId = (int) ($request->user()?->helpdeskProfile?->staff_id ?? 0);
        if ($staffId <= 0) {
            return false;
        }
        if ($row->hod_staff_id && (int) $row->hod_staff_id === $staffId) {
            return true;
        }
        $divId = (int) ($row->requester_division_id ?? 0);
        if ($divId <= 0) {
            return false;
        }

        return $heads->effectiveHeadStaffIdForDivision($divId) === $staffId;
    }

    private function divisionNameFromCache(int $divisionId): ?string
    {
        $bundle = Cache::get(DivisionHeadResolver::CACHE_KEY);
        $divisions = is_array($bundle['divisions'] ?? null) ? $bundle['divisions'] : [];
        foreach ($divisions as $d) {
            if (is_array($d) && (int) ($d['id'] ?? 0) === $divisionId) {
                $name = (string) ($d['name'] ?? '');

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    private function staffDisplayName(int $staffId, StaffDirectoryLookupService $directory): ?string
    {
        $resolved = $directory->resolveByStaffId($staffId);
        if ($resolved === null) {
            return 'Staff '.$staffId;
        }
        $name = trim((string) ($resolved['name'] ?? ''));

        return $name !== '' ? $name : 'Staff '.$staffId;
    }
}
