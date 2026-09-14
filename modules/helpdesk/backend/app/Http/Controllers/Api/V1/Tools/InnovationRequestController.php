<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskInnovationRequest;
use App\Services\DivisionHeadResolver;
use App\Services\HtmlSanitizer;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InnovationRequestController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        $canProcess = $profile && $profile->canProcessInnovationRequests();

        $query = HelpdeskInnovationRequest::query()->orderByDesc('updated_at');

        if (! $canProcess) {
            $query->where('requester_user_id', $user?->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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

    public function show(Request $request, HelpdeskInnovationRequest $innovationRequest): JsonResponse
    {
        $this->assertCanView($request, $innovationRequest);

        return response()->json(['data' => $innovationRequest->load('requester:id,name,email')]);
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

        $row = HelpdeskInnovationRequest::query()->create([
            ...$validated,
            'request_number' => HelpdeskInnovationRequest::generateRequestNumber(),
            'status' => $submit ? HelpdeskInnovationRequest::STATUS_SUBMITTED : HelpdeskInnovationRequest::STATUS_DRAFT,
            'requester_user_id' => $user->id,
            'requester_staff_id' => $profile?->staff_id,
            'requester_name' => $validated['requester_name'] ?? $user->name,
            'requester_division_id' => $divisionId,
            'requester_division_name' => $divisionName,
            'created_by_user_id' => $user->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskInnovationRequest $innovationRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user
            && (int) $innovationRequest->requester_user_id === (int) $user->id
            && in_array($innovationRequest->status, [
                HelpdeskInnovationRequest::STATUS_DRAFT,
                HelpdeskInnovationRequest::STATUS_REJECTED,
            ], true),
            403,
        );

        $validated = $this->validatedPayload($request, true);
        $submit = $request->boolean('submit');
        $innovationRequest->fill($validated);
        if ($submit) {
            $innovationRequest->status = HelpdeskInnovationRequest::STATUS_SUBMITTED;
        }
        $innovationRequest->save();

        return response()->json(['data' => $innovationRequest->fresh()]);
    }

    public function process(Request $request, HelpdeskInnovationRequest $innovationRequest): JsonResponse
    {
        $this->ensureInnovationProcess($request);
        abort_unless(
            $innovationRequest->status === HelpdeskInnovationRequest::STATUS_SUBMITTED,
            403,
            'Only submitted innovation requests can be processed.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $innovationRequest->status = HelpdeskInnovationRequest::STATUS_IN_PROGRESS;
        $innovationRequest->processed_by_user_id = $user->id;
        $innovationRequest->processed_at = now();
        $innovationRequest->process_notes = $notes;
        $innovationRequest->save();

        return response()->json(['data' => $innovationRequest->fresh()]);
    }

    public function complete(Request $request, HelpdeskInnovationRequest $innovationRequest): JsonResponse
    {
        $this->ensureInnovationProcess($request);
        abort_unless(
            $innovationRequest->status === HelpdeskInnovationRequest::STATUS_IN_PROGRESS,
            422,
            'Only in-progress requests can be completed.',
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        if ($notes !== null) {
            $innovationRequest->process_notes = $notes;
        }
        $innovationRequest->status = HelpdeskInnovationRequest::STATUS_COMPLETED;
        $innovationRequest->save();

        return response()->json(['data' => $innovationRequest->fresh()]);
    }

    public function reject(Request $request, HelpdeskInnovationRequest $innovationRequest): JsonResponse
    {
        $this->ensureInnovationProcess($request);
        abort_unless(
            in_array($innovationRequest->status, [
                HelpdeskInnovationRequest::STATUS_SUBMITTED,
                HelpdeskInnovationRequest::STATUS_IN_PROGRESS,
            ], true),
            422,
        );

        $notes = $request->validate(['notes' => ['nullable', 'string']])['notes'] ?? null;
        $user = $request->user();

        $innovationRequest->status = HelpdeskInnovationRequest::STATUS_REJECTED;
        $innovationRequest->processed_by_user_id = $user->id;
        $innovationRequest->processed_at = now();
        $innovationRequest->process_notes = $notes;
        $innovationRequest->save();

        return response()->json(['data' => $innovationRequest->fresh()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $validated = $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:65000'],
            'innovation_type' => ['nullable', 'string', 'max:191'],
            'requester_name' => ['nullable', 'string', 'max:191'],
            'on_behalf_of_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['description']) && is_string($validated['description'])) {
            $validated['description'] = HtmlSanitizer::sanitize($validated['description']);
        }

        return $validated;
    }

    private function assertCanView(Request $request, HelpdeskInnovationRequest $row): void
    {
        $user = $request->user();
        $profile = $user?->helpdeskProfile;
        if ($profile && $profile->canProcessInnovationRequests()) {
            return;
        }
        if ($user && (int) $row->requester_user_id === (int) $user->id) {
            return;
        }
        abort(403);
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
}
