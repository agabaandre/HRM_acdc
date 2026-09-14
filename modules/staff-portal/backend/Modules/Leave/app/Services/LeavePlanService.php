<?php

namespace Modules\Leave\Services;

use App\Support\LegacySchema;
use App\Support\PortalReadCache;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Models\StaffLeavePlan;
use Modules\Leave\Models\StaffLeavePlanEntry;
use RuntimeException;

class LeavePlanService
{
    /** @var object{leave_id: int|string, leave_name: string, code?: string|null}|false|null */
    protected object|false|null $annualTypeMemo = null;

    public function __construct(
        protected LeaveRequestService $requests,
        protected LeaveBalanceService $balances,
    ) {}

    public function tablesReady(): bool
    {
        return LegacySchema::has('staff_leave_plans')
            && LegacySchema::has('staff_leave_plan_entries');
    }

    /**
     * Get or create the staff plan for a calendar year.
     */
    public function getOrCreateForStaff(int $staffId, int $year): StaffLeavePlan
    {
        if (! $this->tablesReady()) {
            throw new RuntimeException('Leave plan tables are missing. Run migrations.');
        }

        $plan = StaffLeavePlan::query()
            ->where('staff_id', $staffId)
            ->where('plan_year', $year)
            ->first();

        if ($plan) {
            return $plan->load(['entries']);
        }

        $plan = StaffLeavePlan::query()->create([
            'staff_id' => $staffId,
            'plan_year' => $year,
            'draft_status' => StaffLeavePlan::STATUS_DRAFT,
            'notes' => null,
        ]);

        return $plan->load(['entries']);
    }

    /**
     * Annual leave type only — leave plans do not cover other leave types.
     *
     * @return object{leave_id: int|string, leave_name: string, code?: string|null}|null
     */
    public function resolveAnnualLeaveType(): ?object
    {
        if ($this->annualTypeMemo !== null) {
            return $this->annualTypeMemo === false ? null : $this->annualTypeMemo;
        }

        $cached = PortalReadCache::remember(
            PortalReadCache::key('leave', 'annual_type', 0),
            function (): ?object {
                return DB::table('leave_types')
                    ->where(function ($q): void {
                        $q->where('leave_name', 'like', '%annual%')
                            ->orWhere('code', 'like', '%annual%');
                    })
                    ->orderBy('leave_id')
                    ->first();
            }
        );

        if ($cached === null) {
            $this->annualTypeMemo = false;

            return null;
        }

        // Cache may hydrate as array depending on store.
        $this->annualTypeMemo = is_array($cached) ? (object) $cached : $cached;

        return $this->annualTypeMemo;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StaffLeavePlan $plan, int $staffId): array
    {
        $plan->loadMissing(['entries']);
        $readonly = ! $plan->isDraft();
        $annualType = $this->resolveAnnualLeaveType();
        $annualLeaveId = $annualType ? (int) $annualType->leave_id : null;
        $annualLeaveName = $annualType ? (string) $annualType->leave_name : 'Annual leave';

        $entries = $plan->entries->map(fn (StaffLeavePlanEntry $e) => [
            'id' => (int) $e->id,
            'leave_id' => $annualLeaveId ?? (int) $e->leave_id,
            'leave_name' => $annualLeaveName,
            'start_date' => $e->start_date,
            'end_date' => $e->end_date,
            'planned_days' => (float) $e->planned_days,
            'remarks' => $e->remarks,
            'sort_order' => (int) $e->sort_order,
        ])->values()->all();

        $plannedTotal = 0.0;
        foreach ($entries as $entry) {
            $plannedTotal += (float) $entry['planned_days'];
        }

        $balanceHint = null;
        if ($annualType) {
            $snap = $this->balances->snapshot($staffId, (int) $annualType->leave_id, (int) $plan->plan_year);
            $balanceHint = [
                'leave_id' => (int) $annualType->leave_id,
                'leave_name' => (string) $annualType->leave_name,
                'available' => (float) ($snap['available'] ?? 0),
                'entitlement' => (float) ($snap['entitlement'] ?? 0),
            ];
        }

        return [
            'id' => (int) $plan->id,
            'staff_id' => (int) $plan->staff_id,
            'plan_year' => (int) $plan->plan_year,
            'draft_status' => (int) $plan->draft_status,
            'status_label' => $plan->isDraft() ? 'Draft' : 'Submitted',
            'submitted_at' => $plan->submitted_at?->toIso8601String(),
            'notes' => $plan->notes,
            'entries' => $entries,
            'planned_days_total' => (float) $plannedTotal,
            'readonly' => $readonly,
            'can_save' => ! $readonly,
            'can_submit' => ! $readonly && $entries !== [],
            'balance_hint' => $balanceHint,
            'annual_leave' => $annualType ? [
                'leave_id' => (int) $annualType->leave_id,
                'leave_name' => (string) $annualType->leave_name,
            ] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function saveDraft(StaffLeavePlan $plan, array $entries, ?string $notes = null): StaffLeavePlan
    {
        if (! $plan->isDraft()) {
            throw new RuntimeException('Submitted leave plans cannot be edited.');
        }

        $annualType = $this->resolveAnnualLeaveType();
        if (! $annualType) {
            throw new RuntimeException('Annual leave type is not configured. Ask HR to set up leave types.');
        }
        $leaveId = (int) $annualType->leave_id;

        $year = (int) $plan->plan_year;
        $normalized = [];
        foreach (array_values($entries) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $start = trim((string) ($row['start_date'] ?? ''));
            $end = trim((string) ($row['end_date'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }
            if ($start > $end) {
                throw new RuntimeException('Each plan row needs end date on or after start date.');
            }
            $startYear = (int) date('Y', strtotime($start));
            $endYear = (int) date('Y', strtotime($end));
            if ($startYear !== $year || $endYear !== $year) {
                throw new RuntimeException("Plan dates must fall within calendar year {$year}.");
            }

            $days = isset($row['planned_days']) && $row['planned_days'] !== '' && $row['planned_days'] !== null
                ? (float) $row['planned_days']
                : (float) $this->requests->workingDaysBetween($start, $end);

            if ($days <= 0) {
                throw new RuntimeException('Planned days must be greater than zero.');
            }

            $normalized[] = [
                'leave_id' => $leaveId,
                'start_date' => $start,
                'end_date' => $end,
                'planned_days' => $days,
                'remarks' => trim((string) ($row['remarks'] ?? '')) ?: null,
                'sort_order' => $i,
            ];
        }

        DB::transaction(function () use ($plan, $normalized, $notes): void {
            $plan->entries()->delete();
            foreach ($normalized as $row) {
                $plan->entries()->create($row);
            }
            $plan->notes = $notes;
            $plan->save();
        });

        return $plan->fresh(['entries']);
    }

    public function submit(StaffLeavePlan $plan, int $userId): StaffLeavePlan
    {
        if (! $plan->isDraft()) {
            throw new RuntimeException('This leave plan is already submitted.');
        }

        $plan->load('entries');
        if ($plan->entries->isEmpty()) {
            throw new RuntimeException('Add at least one planned leave period before submitting.');
        }

        $plan->draft_status = StaffLeavePlan::STATUS_SUBMITTED;
        $plan->submitted_at = now();
        $plan->submitted_by_user_id = $userId;
        $plan->save();

        return $plan->fresh(['entries']);
    }
}
