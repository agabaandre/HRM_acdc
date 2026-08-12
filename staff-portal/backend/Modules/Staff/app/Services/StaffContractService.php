<?php

namespace Modules\Staff\Services;

use App\Support\StaffContractFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffContractService
{
    public const CURRENT_STATUSES = [1, 2, 7];

    /**
     * @return array<string, mixed>
     */
    public function formLookups(int $excludeStaffId = 0): array
    {
        $supervisors = DB::table('staff')
            ->when($excludeStaffId > 0, fn ($q) => $q->where('staff_id', '!=', $excludeStaffId))
            ->orderBy('lname')
            ->orderBy('fname')
            ->select('staff_id', 'fname', 'lname')
            ->get();

        return [
            'jobs' => DB::table('jobs')->orderBy('job_name')->get(),
            'jobsActing' => DB::table('jobs_acting')->orderBy('job_acting')->get(),
            'grades' => DB::table('grades')->orderBy('grade')->get(),
            'institutions' => DB::table('contracting_institutions')->orderBy('contracting_institution')->get(),
            'funders' => DB::table('funders')->orderBy('funder')->get(),
            'contractTypes' => DB::table('contract_types')->orderBy('contract_type')->get(),
            'dutyStations' => DB::table('duty_stations')->orderBy('duty_station_name')->get(),
            'divisions' => DB::table('divisions')->orderBy('division_name')->get(),
            'units' => DB::getSchemaBuilder()->hasTable('units')
                ? DB::table('units')->orderBy('unit_name')->get()
                : [],
            'statuses' => DB::table('status')->orderBy('status_id')->get(),
            'nationalities' => DB::table('nationalities')->orderBy('nationality')->get(),
            'supervisors' => $supervisors,
        ];
    }

    /**
     * @return list<object>
     */
    public function unitsForDivision(int $divisionId): array
    {
        if ($divisionId < 1 || ! DB::getSchemaBuilder()->hasTable('units')) {
            return [];
        }

        return DB::table('units')
            ->where('division_id', $divisionId)
            ->orderBy('unit_name')
            ->get()
            ->all();
    }

    public function latestContract(int $staffId): ?object
    {
        return DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->first();
    }

    public function previousContractStatus(int $staffId): ?int
    {
        $row = $this->latestContract($staffId);

        return $row ? (int) $row->status_id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function renewDefaults(int $staffId): array
    {
        $latest = $this->latestContract($staffId);
        $other = $this->parseOtherDivisions($latest->other_associated_divisions ?? null);

        return [
            'job_id' => (int) ($latest->job_id ?? 0),
            'job_acting_id' => (int) ($latest->job_acting_id ?? 0) ?: '',
            'grade_id' => (string) ($latest->grade_id ?? ''),
            'contracting_institution_id' => (int) ($latest->contracting_institution_id ?? 0),
            'funder_id' => (int) ($latest->funder_id ?? 0),
            'first_supervisor' => (int) ($latest->first_supervisor ?? 0),
            'second_supervisor' => (int) ($latest->second_supervisor ?? 0) ?: '',
            'contract_type_id' => (int) ($latest->contract_type_id ?? 0),
            'duty_station_id' => (int) ($latest->duty_station_id ?? 0),
            'division_id' => (int) ($latest->division_id ?? 0),
            'unit_id' => (int) ($latest->unit_id ?? 1),
            'other_associated_divisions' => $other,
            'start_date' => '',
            'end_date' => '',
            'status_id' => 1,
            'previous_contract_status_id' => '',
            'comments' => '',
            'file_name' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rowToForm(object $row): array
    {
        return [
            'job_id' => (int) ($row->job_id ?? 0),
            'job_acting_id' => (int) ($row->job_acting_id ?? 0) ?: '',
            'grade_id' => (string) ($row->grade_id ?? ''),
            'contracting_institution_id' => (int) ($row->contracting_institution_id ?? 0),
            'funder_id' => (int) ($row->funder_id ?? 0),
            'first_supervisor' => (int) ($row->first_supervisor ?? 0),
            'second_supervisor' => (int) ($row->second_supervisor ?? 0) ?: '',
            'contract_type_id' => (int) ($row->contract_type_id ?? 0),
            'duty_station_id' => (int) ($row->duty_station_id ?? 0),
            'division_id' => (int) ($row->division_id ?? 0),
            'unit_id' => (int) ($row->unit_id ?? 1),
            'other_associated_divisions' => $this->parseOtherDivisions($row->other_associated_divisions ?? null),
            'start_date' => (string) ($row->start_date ?? ''),
            'end_date' => (string) ($row->end_date ?? ''),
            'status_id' => (int) ($row->status_id ?? 1),
            'comments' => (string) ($row->comments ?? ''),
            'file_name' => (string) ($row->file_name ?? ''),
        ];
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function encodeOtherDivisions(array $ids): ?string
    {
        $filtered = array_values(array_filter(array_map('intval', $ids)));
        if ($filtered === []) {
            return null;
        }

        return json_encode($filtered);
    }

    /**
     * @return list<int>
     */
    public function parseOtherDivisions(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } else {
            $decoded = $value;
        }

        return is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
    }

    public function otherDivisionLabels(mixed $value): string
    {
        $ids = $this->parseOtherDivisions($value);
        if ($ids === []) {
            return '—';
        }
        $names = DB::table('divisions')->whereIn('division_id', $ids)->pluck('division_name', 'division_id');

        return collect($ids)->map(fn (int $id) => $names[$id] ?? (string) $id)->implode(', ');
    }

    /**
     * @return Collection<int, object>
     */
    public function editableStatuses(int $currentStatusId): Collection
    {
        $all = DB::table('status')->orderBy('status_id')->get();

        return $all->filter(function (object $st) use ($currentStatusId): bool {
            $id = (int) $st->status_id;
            $allowed = in_array($id, [1, 4, 7], true);

            return $allowed || $id === $currentStatusId;
        })->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function renewNewStatuses(): Collection
    {
        return DB::table('status')->whereIn('status_id', [1, 4, 7])->orderBy('status_id')->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function renewPreviousStatuses(?int $lockedStatus): Collection
    {
        if ($lockedStatus === 4) {
            return DB::table('status')->where('status_id', 4)->get();
        }

        return DB::table('status')->whereIn('status_id', [5, 6])->orderBy('status_id')->get();
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public function create(int $staffId, array $form, ?UploadedFile $pdf = null): ?int
    {
        $payload = $this->buildPayload($form);
        $payload['staff_id'] = $staffId;

        $id = (int) DB::table('staff_contracts')->insertGetId($payload);
        if ($id < 1) {
            return null;
        }

        if ($pdf) {
            $stored = $this->storePdf($pdf, $staffId, $id);
            DB::table('staff_contracts')->where('staff_contract_id', $id)->update(['file_name' => $stored]);
        }

        $this->syncContractStatusFromEndDate($id, $staffId);
        $statusId = (int) DB::table('staff_contracts')->where('staff_contract_id', $id)->value('status_id');
        if (in_array($statusId, self::CURRENT_STATUSES, true)) {
            $this->demotePreviousOnRenew($staffId, $id);
        }

        $this->markEmailEnabledIfActive($staffId, $statusId);

        return $id;
    }

    public function applyPreviousContractStatus(int $staffId, int $newContractId, int $previousStatusId): void
    {
        $this->demotePreviousOnRenew($staffId, $newContractId);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public function update(int $contractId, int $staffId, array $form, ?UploadedFile $pdf = null): bool
    {
        $existing = DB::table('staff_contracts')
            ->where('staff_contract_id', $contractId)
            ->where('staff_id', $staffId)
            ->first();

        if (! $existing) {
            return false;
        }

        $payload = $this->buildPayload($form);
        $this->assertNoConflictingCurrent($staffId, $contractId, $this->resolvedStatusId(
            (int) $payload['status_id'],
            (string) $payload['end_date']
        ));

        if ($pdf) {
            $payload['file_name'] = $this->storePdf($pdf, $staffId, $contractId);
        }

        DB::table('staff_contracts')
            ->where('staff_contract_id', $contractId)
            ->update($payload);

        $this->syncPpaSupervisor($staffId, (int) ($form['first_supervisor'] ?? 0));
        $this->syncContractStatusFromEndDate($contractId, $staffId);
        $statusId = (int) DB::table('staff_contracts')->where('staff_contract_id', $contractId)->value('status_id');
        $this->markEmailEnabledIfActive($staffId, $statusId);

        return true;
    }

    public function assertNoConflictingCurrent(int $staffId, ?int $exceptContractId, int $incomingStatusId): void
    {
        if (! in_array($incomingStatusId, self::CURRENT_STATUSES, true)) {
            return;
        }

        $query = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->whereIn('status_id', self::CURRENT_STATUSES);

        if ($exceptContractId !== null) {
            $query->where('staff_contract_id', '!=', $exceptContractId);
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'status_id' => 'A current contract already exists for this staff member.',
        ]);
    }

    public function demotePreviousOnRenew(int $staffId, int $newContractId): void
    {
        $previousContracts = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->where('staff_contract_id', '!=', $newContractId)
            ->orderByDesc('staff_contract_id')
            ->get(['staff_contract_id', 'status_id']);

        if ($previousContracts->isEmpty()) {
            return;
        }

        $latestPriorId = (int) $previousContracts->first()->staff_contract_id;
        $renewedIds = [];

        foreach ($previousContracts as $contract) {
            $contractId = (int) $contract->staff_contract_id;
            $statusId = (int) $contract->status_id;

            if ($contractId === $latestPriorId) {
                if ($statusId !== 3) {
                    $renewedIds[] = $contractId;
                }

                continue;
            }

            if (in_array($statusId, self::CURRENT_STATUSES, true)) {
                $renewedIds[] = $contractId;
            }
        }

        if ($renewedIds === []) {
            return;
        }

        DB::table('staff_contracts')
            ->whereIn('staff_contract_id', array_values(array_unique($renewedIds)))
            ->update(['status_id' => 6]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function buildPayload(array $form): array
    {
        $second = $form['second_supervisor'] ?? null;
        $acting = $form['job_acting_id'] ?? null;
        $unit = $form['unit_id'] ?? null;

        return [
            'job_id' => (int) $form['job_id'],
            'job_acting_id' => $acting !== '' && $acting !== null ? (int) $acting : null,
            'grade_id' => (string) $form['grade_id'],
            'contracting_institution_id' => (int) $form['contracting_institution_id'],
            'funder_id' => (int) $form['funder_id'],
            'first_supervisor' => (int) $form['first_supervisor'],
            'second_supervisor' => $second !== '' && $second !== null ? (int) $second : null,
            'contract_type_id' => (int) $form['contract_type_id'],
            'duty_station_id' => (int) $form['duty_station_id'],
            'division_id' => (int) $form['division_id'],
            'unit_id' => $unit !== '' && $unit !== null ? (int) $unit : null,
            'other_associated_divisions' => $this->encodeOtherDivisions($form['other_associated_divisions'] ?? []),
            'start_date' => $form['start_date'],
            'end_date' => $form['end_date'],
            'status_id' => (int) $form['status_id'],
            'comments' => (string) ($form['comments'] ?? ''),
        ];
    }

    public function storePdf(UploadedFile $file, int $staffId, int $contractId): string
    {
        StaffContractFile::ensureDirectory();
        $name = sprintf('contract_%d_%d_%s.pdf', $staffId, $contractId, now()->format('YmdHis'));
        $file->move(dirname(StaffContractFile::uploadsPath($name)), $name);

        return $name;
    }

    private function markEmailEnabledIfActive(int $staffId, int $statusId): void
    {
        if ($statusId !== 1) {
            return;
        }

        DB::table('staff')->where('staff_id', $staffId)->update([
            'email_disabled_by' => 0,
            'email_status' => 1,
            'email_disabled_at' => now(),
        ]);
    }

    private function syncPpaSupervisor(int $staffId, int $supervisorId): void
    {
        if ($supervisorId < 1 || ! DB::getSchemaBuilder()->hasTable('ppa_entries')) {
            return;
        }

        DB::table('ppa_entries')
            ->where('staff_id', $staffId)
            ->whereIn('draft_status', [0, 1])
            ->orderByDesc('entry_id')
            ->limit(1)
            ->update([
                'supervisor_id' => $supervisorId,
                'updated_at' => now(),
            ]);
    }

    public function syncContractStatusFromEndDate(int $contractId, int $staffId): void
    {
        $row = DB::table('staff_contracts')
            ->where('staff_contract_id', $contractId)
            ->where('staff_id', $staffId)
            ->first();

        if (! $row) {
            return;
        }

        $currentStatus = (int) $row->status_id;
        $computedStatus = $this->statusIdFromEndDate((string) $row->end_date);

        if (in_array($currentStatus, [1, 2, 3], true) && $currentStatus !== $computedStatus) {
            DB::table('staff_contracts')
                ->where('staff_contract_id', $contractId)
                ->update(['status_id' => $computedStatus]);
            $currentStatus = $computedStatus;
        }

        $latestId = (int) DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->value('staff_contract_id');

        if ($latestId === $contractId) {
            DB::table('staff')->where('staff_id', $staffId)->update([
                'flag' => in_array($currentStatus, [2, 3], true) ? 1 : 0,
            ]);

            app(StaffPortalAccountService::class)->syncForStaff($staffId);
        }

        if ($computedStatus === 1) {
            $this->clearContractReminderNotifications($staffId);
        }
    }

    private function statusIdFromEndDate(string $endDate): int
    {
        if ($endDate === '' || $endDate === '0000-00-00') {
            return 3;
        }

        $days = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($endDate)->startOfDay(), false);

        if ($days <= 0) {
            return 3;
        }
        if ($days <= 90) {
            return 2;
        }

        return 1;
    }

    private function resolvedStatusId(int $statusId, string $endDate): int
    {
        if (in_array($statusId, [1, 2, 3], true)) {
            return $this->statusIdFromEndDate($endDate);
        }

        return $statusId;
    }

    private function clearContractReminderNotifications(int $staffId): void
    {
        DB::table('email_notifications')
            ->where('staff_id', $staffId)
            ->whereIn('subject', [
                'Contract Due for Renewal Notice',
                'Expired Contract Notice',
            ])
            ->delete();
    }
}
