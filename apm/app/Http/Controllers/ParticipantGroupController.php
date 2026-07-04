<?php

namespace App\Http\Controllers;

use App\Models\ParticipantGroup;
use App\Models\Staff;
use App\Services\ParticipantGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParticipantGroupController extends Controller
{
    public function __construct(
        private ParticipantGroupService $groups,
    ) {}

    public function index(): View
    {
        if (! $this->groups->canUseDivisionGroups()) {
            abort(403, 'Participant groups are available to staff with a home division.');
        }

        $divisionId = $this->groups->sessionDivisionId();
        $canManage = $this->groups->canManageGroups();

        $savedGroups = ParticipantGroup::query()
            ->where('division_id', $divisionId)
            ->where('is_active', true)
            ->with(['creator:staff_id,fname,lname'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $divisionStaff = Staff::active()
            ->where('division_id', $divisionId)
            ->orderBy('fname')
            ->orderBy('lname')
            ->get(['staff_id', 'fname', 'lname', 'job_name', 'division_name']);

        $allStaffGrouped = Staff::active()
            ->orderBy('division_name')
            ->orderBy('fname')
            ->orderBy('lname')
            ->get(['staff_id', 'fname', 'lname', 'job_name', 'division_name'])
            ->groupBy(fn ($s) => $s->division_name ?: 'Other');

        $staffOptions = $allStaffGrouped->flatMap(function ($members, $divisionName) {
            return $members->map(function ($m) use ($divisionName) {
                $name = trim($m->fname . ' ' . $m->lname);

                return [
                    'value' => (int) $m->staff_id,
                    'title' => $name,
                    'subtitle' => trim(($m->job_name ?: '') . ($divisionName ? ' · ' . $divisionName : ''), ' ·'),
                    'division' => (string) $divisionName,
                ];
            });
        })->values();

        return view('participant-groups.index', [
            'savedGroups' => $savedGroups,
            'canManage' => $canManage,
            'divisionStaffCount' => $divisionStaff->count(),
            'staffOptions' => $staffOptions,
            'minMemoParticipants' => ParticipantGroupService::MIN_MEMO_PARTICIPANTS_FOR_IMPORT,
            'pageConfig' => [
                'canManage' => $canManage,
                'divisionStaffCount' => $divisionStaff->count(),
                'minMemoParticipants' => ParticipantGroupService::MIN_MEMO_PARTICIPANTS_FOR_IMPORT,
                'csrf' => csrf_token(),
                'routes' => [
                    'store' => route('participant-groups.store'),
                    'update' => url('participant-groups'),
                    'destroy' => url('participant-groups'),
                    'show' => url('participant-groups/saved'),
                    'memosForImport' => route('participant-groups.memos-for-import'),
                    'storeFromMemo' => route('participant-groups.store-from-memo'),
                ],
                'groups' => $savedGroups->map(fn ($g) => [
                    'id' => (int) $g->id,
                    'name' => $g->name,
                    'description' => $g->description,
                    'members_count' => (int) $g->members_count,
                    'created_by_name' => $g->creator
                        ? trim($g->creator->fname . ' ' . $g->creator->lname)
                        : null,
                ])->values(),
                'staffOptions' => $staffOptions,
            ],
        ]);
    }

    public function list(): JsonResponse
    {
        if (! $this->groups->canUseDivisionGroups()) {
            return response()->json([]);
        }

        return response()->json($this->groups->listForPicker());
    }

    public function members(string $group): JsonResponse
    {
        if (! $this->groups->canUseDivisionGroups()) {
            return response()->json(['members' => []], 403);
        }

        return response()->json([
            'members' => $this->groups->membersForGroup($group),
        ]);
    }

    public function memosForImport(): JsonResponse
    {
        if (! $this->groups->canManageGroups()) {
            return response()->json(['memos' => []], 403);
        }

        return response()->json([
            'memos' => $this->groups->memosAvailableForImport(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => 'integer|min:1',
        ]);

        try {
            $group = $this->groups->createGroup(
                (string) $request->input('name'),
                array_map('intval', $request->input('staff_ids', [])),
                $request->input('description')
            );

            return response()->json([
                'success' => true,
                'group' => [
                    'id' => (int) $group->id,
                    'name' => $group->name,
                    'member_count' => (int) $group->members_count,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storeFromMemo(Request $request): JsonResponse
    {
        $request->validate([
            'memo_type' => 'required|in:activity,special_memo',
            'memo_id' => 'required|integer|min:1',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $group = $this->groups->createGroupFromMemo(
                (string) $request->input('memo_type'),
                (int) $request->input('memo_id'),
                (string) $request->input('name'),
                $request->input('description')
            );

            return response()->json([
                'success' => true,
                'group' => [
                    'id' => (int) $group->id,
                    'name' => $group->name,
                    'member_count' => (int) $group->members_count,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, ParticipantGroup $participantGroup): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => 'integer|min:1',
        ]);

        try {
            $group = $this->groups->updateGroup(
                $participantGroup,
                (string) $request->input('name'),
                array_map('intval', $request->input('staff_ids', [])),
                $request->input('description')
            );

            return response()->json([
                'success' => true,
                'group' => [
                    'id' => (int) $group->id,
                    'name' => $group->name,
                    'member_count' => (int) $group->members_count,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(ParticipantGroup $participantGroup): JsonResponse
    {
        try {
            $this->groups->deleteGroup($participantGroup);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(ParticipantGroup $participantGroup): JsonResponse
    {
        $divisionId = $this->groups->sessionDivisionId();
        if ($divisionId === null || (int) $participantGroup->division_id !== $divisionId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $staffIds = $participantGroup->members()->pluck('staff_id')->all();

        return response()->json([
            'id' => (int) $participantGroup->id,
            'name' => $participantGroup->name,
            'description' => $participantGroup->description,
            'staff_ids' => array_map('intval', $staffIds),
        ]);
    }
}
