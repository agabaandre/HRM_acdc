<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ParticipantGroup;
use App\Models\ParticipantGroupMember;
use App\Models\SpecialMemo;
use App\Models\Staff;
use Illuminate\Support\Collection;

class ParticipantGroupService
{
    public const MIN_MEMO_PARTICIPANTS_FOR_IMPORT = 15;

    public function sessionDivisionId(): ?int
    {
        $id = user_session('division_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function sessionStaffId(): ?int
    {
        return resolved_session_staff_id();
    }

    public function canManageGroups(?int $divisionId = null): bool
    {
        if ((int) user_session('role') === 10) {
            return true;
        }

        $divisionId ??= $this->sessionDivisionId();
        if ($divisionId === null) {
            return false;
        }

        return isfocal_person();
    }

    public function canUseDivisionGroups(?int $divisionId = null): bool
    {
        $divisionId ??= $this->sessionDivisionId();

        return $divisionId !== null && $divisionId > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPicker(?int $divisionId = null): array
    {
        $divisionId ??= $this->sessionDivisionId();
        if ($divisionId === null) {
            return [];
        }

        $groups = [];

        $divisionCount = Staff::active()
            ->where('division_id', $divisionId)
            ->count();

        $groups[] = [
            'id' => ParticipantGroup::VIRTUAL_DIVISION_MEMBERS,
            'name' => 'Division Members',
            'description' => 'All active staff in your division (updated automatically)',
            'member_count' => $divisionCount,
            'is_virtual' => true,
            'created_by_name' => null,
        ];

        $saved = ParticipantGroup::query()
            ->where('division_id', $divisionId)
            ->where('is_active', true)
            ->with(['creator:staff_id,fname,lname'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        foreach ($saved as $group) {
            $groups[] = [
                'id' => (int) $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'member_count' => (int) $group->members_count,
                'is_virtual' => false,
                'created_by_name' => $group->creator
                    ? trim($group->creator->fname . ' ' . $group->creator->lname)
                    : null,
            ];
        }

        return $groups;
    }

    /**
     * @return list<array{id: int, name: string, division_name: string|null}>
     */
    public function membersForGroup(string|int $groupId, ?int $divisionId = null): array
    {
        $divisionId ??= $this->sessionDivisionId();
        if ($divisionId === null) {
            return [];
        }

        if ((string) $groupId === ParticipantGroup::VIRTUAL_DIVISION_MEMBERS) {
            return $this->staffPickerRows(
                Staff::active()
                    ->where('division_id', $divisionId)
                    ->orderBy('fname')
                    ->orderBy('lname')
                    ->get(['staff_id', 'fname', 'lname', 'job_name', 'division_name'])
            );
        }

        $group = ParticipantGroup::query()
            ->where('division_id', $divisionId)
            ->where('is_active', true)
            ->find((int) $groupId);

        if (! $group) {
            return [];
        }

        $staffIds = $group->members()->pluck('staff_id')->all();
        if ($staffIds === []) {
            return [];
        }

        $staff = Staff::active()
            ->whereIn('staff_id', $staffIds)
            ->get(['staff_id', 'fname', 'lname', 'job_name', 'division_name'])
            ->keyBy('staff_id');

        $rows = [];
        foreach ($staffIds as $staffId) {
            $member = $staff->get((int) $staffId);
            if (! $member) {
                continue;
            }
            $rows[] = $this->staffToPickerRow($member);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $staffIds
     */
    public function createGroup(string $name, array $staffIds, ?string $description = null, ?int $divisionId = null): ParticipantGroup
    {
        $divisionId ??= $this->sessionDivisionId();
        $createdBy = $this->sessionStaffId();

        if ($divisionId === null || $createdBy === null) {
            throw new \InvalidArgumentException('Unable to determine your division.');
        }

        if (! $this->canManageGroups($divisionId)) {
            throw new \RuntimeException('Only focal persons can create participant groups.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Group name is required.');
        }

        if (strcasecmp($name, 'Division Members') === 0) {
            throw new \InvalidArgumentException('That name is reserved for the automatic division group.');
        }

        $staffIds = $this->normalizeStaffIds($staffIds);
        if ($staffIds === []) {
            throw new \InvalidArgumentException('Select at least one participant.');
        }

        $group = ParticipantGroup::query()->create([
            'division_id' => $divisionId,
            'name' => $name,
            'description' => $description ? trim($description) : null,
            'created_by' => $createdBy,
            'is_active' => true,
        ]);

        $this->syncMembers($group, $staffIds);

        return $group->loadCount('members');
    }

    /**
     * @param  list<int>  $staffIds
     */
    public function updateGroup(ParticipantGroup $group, string $name, array $staffIds, ?string $description = null): ParticipantGroup
    {
        $this->assertCanEditGroup($group);

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Group name is required.');
        }

        if (strcasecmp($name, 'Division Members') === 0) {
            throw new \InvalidArgumentException('That name is reserved for the automatic division group.');
        }

        $staffIds = $this->normalizeStaffIds($staffIds);
        if ($staffIds === []) {
            throw new \InvalidArgumentException('Select at least one participant.');
        }

        $group->update([
            'name' => $name,
            'description' => $description ? trim($description) : null,
        ]);

        $this->syncMembers($group, $staffIds);

        return $group->fresh()->loadCount('members');
    }

    public function deleteGroup(ParticipantGroup $group): void
    {
        $this->assertCanEditGroup($group);
        $group->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function memosAvailableForImport(?int $divisionId = null): array
    {
        $divisionId ??= $this->sessionDivisionId();
        if ($divisionId === null) {
            return [];
        }

        $out = [];

        $activities = Activity::query()
            ->where('division_id', $divisionId)
            ->whereNotNull('internal_participants')
            ->where('internal_participants', '!=', '')
            ->where('internal_participants', '!=', '{}')
            ->where('internal_participants', '!=', '[]')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'activity_title', 'document_number', 'internal_participants', 'updated_at']);

        foreach ($activities as $activity) {
            $ids = $this->participantIdsFromJson($activity->internal_participants);
            if (count($ids) < self::MIN_MEMO_PARTICIPANTS_FOR_IMPORT) {
                continue;
            }
            $out[] = [
                'type' => 'activity',
                'id' => (int) $activity->id,
                'title' => (string) $activity->activity_title,
                'document_number' => $activity->document_number,
                'participant_count' => count($ids),
                'updated_at' => optional($activity->updated_at)->toDateString(),
            ];
        }

        $memos = SpecialMemo::query()
            ->where('division_id', $divisionId)
            ->whereNotNull('internal_participants')
            ->where('internal_participants', '!=', '')
            ->where('internal_participants', '!=', '{}')
            ->where('internal_participants', '!=', '[]')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'activity_title', 'document_number', 'internal_participants', 'updated_at']);

        foreach ($memos as $memo) {
            $ids = $this->participantIdsFromJson($memo->internal_participants);
            if (count($ids) < self::MIN_MEMO_PARTICIPANTS_FOR_IMPORT) {
                continue;
            }
            $out[] = [
                'type' => 'special_memo',
                'id' => (int) $memo->id,
                'title' => (string) $memo->activity_title,
                'document_number' => $memo->document_number,
                'participant_count' => count($ids),
                'updated_at' => optional($memo->updated_at)->toDateString(),
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return array_slice($out, 0, 50);
    }

    public function createGroupFromMemo(string $type, int $memoId, string $name, ?string $description = null): ParticipantGroup
    {
        $divisionId = $this->sessionDivisionId();
        if ($divisionId === null) {
            throw new \InvalidArgumentException('Unable to determine your division.');
        }

        $participantsJson = match ($type) {
            'activity' => Activity::query()->where('division_id', $divisionId)->find($memoId)?->internal_participants,
            'special_memo' => SpecialMemo::query()->where('division_id', $divisionId)->find($memoId)?->internal_participants,
            default => null,
        };

        if ($participantsJson === null) {
            throw new \InvalidArgumentException('Memo not found.');
        }

        $staffIds = $this->participantIdsFromJson($participantsJson);
        if (count($staffIds) < self::MIN_MEMO_PARTICIPANTS_FOR_IMPORT) {
            throw new \InvalidArgumentException('Memo must have at least ' . self::MIN_MEMO_PARTICIPANTS_FOR_IMPORT . ' internal participants.');
        }

        return $this->createGroup($name, $staffIds, $description, $divisionId);
    }

    /**
     * @return list<int>
     */
    public function participantIdsFromJson(mixed $json): array
    {
        if (is_string($json) && $json !== '') {
            $json = json_decode($json, true);
        }
        if (! is_array($json)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            'intval',
            array_keys($json)
        ), fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<int>  $staffIds
     */
    private function syncMembers(ParticipantGroup $group, array $staffIds): void
    {
        ParticipantGroupMember::query()->where('participant_group_id', $group->id)->delete();

        $order = 0;
        foreach ($staffIds as $staffId) {
            ParticipantGroupMember::query()->create([
                'participant_group_id' => $group->id,
                'staff_id' => $staffId,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * @param  list<int>  $staffIds
     * @return list<int>
     */
    private function normalizeStaffIds(array $staffIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $staffIds), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $existing = Staff::active()->whereIn('staff_id', $ids)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($ids, $existing));
    }

    private function assertCanEditGroup(ParticipantGroup $group): void
    {
        $divisionId = $this->sessionDivisionId();
        if ($divisionId === null || (int) $group->division_id !== $divisionId) {
            throw new \RuntimeException('You cannot edit groups from another division.');
        }

        if ($this->canManageGroups($divisionId)) {
            return;
        }

        $staffId = $this->sessionStaffId();
        if ($staffId !== null && (int) $group->created_by === $staffId) {
            return;
        }

        throw new \RuntimeException('You do not have permission to edit this group.');
    }

    /**
     * @param  Collection<int, Staff>  $staff
     * @return list<array{id: int, name: string, division_name: string|null}>
     */
    private function staffPickerRows(Collection $staff): array
    {
        return $staff->map(fn (Staff $s) => $this->staffToPickerRow($s))->values()->all();
    }

    /**
     * @return array{id: int, name: string, division_name: string|null}
     */
    private function staffToPickerRow(Staff $staff): array
    {
        $name = trim($staff->fname . ' ' . $staff->lname);
        if ($staff->job_name) {
            $name .= ' (' . $staff->job_name . ')';
        }

        return [
            'id' => (int) $staff->staff_id,
            'name' => $name,
            'division_name' => $staff->division_name,
        ];
    }
}
