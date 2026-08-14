<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Models\StaffLeaveCompensatoryCredit;
use Modules\Leave\Models\StaffLeaveOpeningBalance;
use Modules\Staff\Models\Staff;

class LeaveBalanceService
{
    public function __construct(
        protected LeavePolicyService $policy,
    ) {}

    /**
     * @return array{
     *     entitlement: float,
     *     accrued: float,
     *     opening: float,
     *     carried_forward: float,
     *     compensatory: float,
     *     used: float,
     *     pending: float,
     *     available: float,
     *     year: int,
     *     has_opening_record: bool
     * }
     */
    public function snapshot(int $staffId, int $leaveTypeId, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        $type = LeaveType::query()->findOrFail($leaveTypeId);
        $opening = $this->openingRecord($staffId, $leaveTypeId, $year);
        $hasOpening = $opening !== null;

        $openingDays = (float) ($opening?->opening_days ?? 0);
        $carriedForward = (float) ($opening?->carried_forward_days ?? 0);
        $compensatory = $this->compensatoryAvailable($staffId) + (float) ($opening?->compensatory_days ?? 0);

        // When an opening row exists, it is the administered entitlement for non-accrued types
        // (avoids double-counting leave_days + opening_days). Accrued types still accrue.
        $entitlement = ($hasOpening && ! $type->is_accrued)
            ? 0.0
            : $this->entitlementForType($type, $staffId, $year);
        $accrued = $type->is_accrued ? $this->accruedDays($staffId, $type, $year) : 0;
        $used = $this->usedDays($staffId, $leaveTypeId, $year);
        $pending = $this->pendingDays($staffId, $leaveTypeId, $year);

        $available = max(0, $openingDays + $carriedForward + $entitlement + $accrued - $used - $pending);

        return [
            'entitlement' => round($entitlement, 2),
            'accrued' => round($accrued, 2),
            'opening' => round($openingDays, 2),
            'carried_forward' => round($carriedForward, 2),
            'compensatory' => round($compensatory, 2),
            'used' => round($used, 2),
            'pending' => round($pending, 2),
            'available' => round($available, 2),
            'year' => $year,
            'has_opening_record' => $hasOpening,
        ];
    }

    /**
     * @return array<int, array{type: LeaveType, balance: array<string, float|int|bool>}>
     */
    public function allTypesForStaff(int $staffId, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        $types = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('leave_name')
            ->get();

        if ($types->isEmpty()) {
            return [];
        }

        $typeIds = $types->pluck('leave_id')->map(fn ($id) => (int) $id)->all();

        $openings = StaffLeaveOpeningBalance::query()
            ->where('staff_id', $staffId)
            ->where('calendar_year', $year)
            ->whereIn('leave_id', $typeIds)
            ->get()
            ->keyBy(fn (StaffLeaveOpeningBalance $row) => (int) $row->leave_id);

        $compensatoryBase = $this->compensatoryAvailable($staffId);

        $usedByType = StaffLeave::query()
            ->where('staff_id', $staffId)
            ->whereIn('leave_id', $typeIds)
            ->where('overall_status', 'Approved')
            ->whereBetween('start_date', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('leave_id, COALESCE(SUM(requested_days), 0) as total')
            ->groupBy('leave_id')
            ->pluck('total', 'leave_id');

        $pendingByType = StaffLeave::query()
            ->where('staff_id', $staffId)
            ->whereIn('leave_id', $typeIds)
            ->where('overall_status', 'Pending')
            ->whereBetween('start_date', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('leave_id, COALESCE(SUM(requested_days), 0) as total')
            ->groupBy('leave_id')
            ->pluck('total', 'leave_id');

        $staff = Staff::query()->find($staffId);
        $accrualMonths = $this->completedMonthsInYearForStaff($staff, $year);
        $accrualRateDefault = (float) $this->policy->get('annual_accrual_per_month', 2.33);
        $prorate = (bool) $this->policy->get('annual_prorate_mid_year_join', true);

        $result = [];
        foreach ($types as $type) {
            $leaveId = (int) $type->leave_id;
            $opening = $openings->get($leaveId);
            $hasOpening = $opening !== null;
            $openingDays = (float) ($opening?->opening_days ?? 0);
            $carriedForward = (float) ($opening?->carried_forward_days ?? 0);
            $compensatory = $compensatoryBase + (float) ($opening?->compensatory_days ?? 0);

            $entitlement = ($hasOpening && ! $type->is_accrued)
                ? 0.0
                : $this->entitlementForType($type, $staffId, $year);

            $accrued = 0.0;
            if ($type->is_accrued) {
                $rate = (float) ($type->accrual_rate ?: $accrualRateDefault);
                $accrued = round(($prorate ? $accrualMonths : 12) * $rate, 2);
            }

            $used = (float) ($usedByType[$leaveId] ?? 0);
            $pending = (float) ($pendingByType[$leaveId] ?? 0);
            $available = max(0, $openingDays + $carriedForward + $entitlement + $accrued - $used - $pending);

            $result[] = [
                'type' => $type,
                'balance' => [
                    'entitlement' => round($entitlement, 2),
                    'accrued' => round($accrued, 2),
                    'opening' => round($openingDays, 2),
                    'carried_forward' => round($carriedForward, 2),
                    'compensatory' => round($compensatory, 2),
                    'used' => round($used, 2),
                    'pending' => round($pending, 2),
                    'available' => round($available, 2),
                    'year' => $year,
                    'has_opening_record' => $hasOpening,
                ],
            ];
        }

        return $result;
    }

    public function compensatoryAvailable(int $staffId): float
    {
        $remaining = StaffLeaveCompensatoryCredit::query()
            ->where('staff_id', $staffId)
            ->where(function ($q): void {
                $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString());
            })
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(days, 0) - COALESCE(days_used, 0), 0)), 0) as remaining')
            ->value('remaining');

        return (float) $remaining;
    }

    protected function openingRecord(int $staffId, int $leaveTypeId, int $year): ?StaffLeaveOpeningBalance
    {
        return StaffLeaveOpeningBalance::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('calendar_year', $year)
            ->first();
    }

    protected function entitlementForType(LeaveType $type, int $staffId, int $year): float
    {
        if ($type->is_accrued) {
            return 0;
        }

        if ($type->max_days_per_year !== null) {
            return (float) $type->max_days_per_year;
        }

        return (float) ($type->leave_days ?? 0);
    }

    protected function accruedDays(int $staffId, LeaveType $type, int $year): float
    {
        $rate = (float) ($type->accrual_rate ?: $this->policy->get('annual_accrual_per_month', 2.33));
        $months = $this->completedMonthsInYear($staffId, $year);

        if ($this->policy->get('annual_prorate_mid_year_join', true)) {
            return round($months * $rate, 2);
        }

        return round(12 * $rate, 2);
    }

    protected function completedMonthsInYear(int $staffId, int $year): int
    {
        return $this->completedMonthsInYearForStaff(Staff::query()->find($staffId), $year);
    }

    protected function completedMonthsInYearForStaff(?Staff $staff, int $year): int
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $now = now();

        $employedRaw = $staff->initiation_date ?? $staff->date_of_birth ?? null;
        if ($staff && ! empty($employedRaw)) {
            try {
                $employed = Carbon::parse($employedRaw)->startOfDay();
                if ($employed->year === $year && $employed->gt($start)) {
                    $start = $employed;
                }
            } catch (\Throwable) {
                //
            }
        }

        if ($now->year < $year) {
            return 0;
        }

        $periodEnd = $now->year === $year ? $now : $end;

        return max(0, (int) $start->diffInMonths($periodEnd) + 1);
    }

    protected function usedDays(int $staffId, int $leaveTypeId, int $year): float
    {
        return (float) StaffLeave::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('overall_status', 'Approved')
            ->whereBetween('start_date', ["{$year}-01-01", "{$year}-12-31"])
            ->sum('requested_days');
    }

    protected function pendingDays(int $staffId, int $leaveTypeId, int $year): float
    {
        return (float) StaffLeave::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('overall_status', 'Pending')
            ->whereBetween('start_date', ["{$year}-01-01", "{$year}-12-31"])
            ->sum('requested_days');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows  keyed by leave_id
     */
    public function saveOpeningBalances(int $staffId, int $year, array $rows, ?int $userId = null): void
    {
        foreach ($rows as $leaveId => $data) {
            StaffLeaveOpeningBalance::query()->updateOrCreate(
                [
                    'staff_id' => $staffId,
                    'leave_id' => (int) $leaveId,
                    'calendar_year' => $year,
                ],
                [
                    'opening_days' => (float) ($data['opening_days'] ?? 0),
                    'carried_forward_days' => (float) ($data['carried_forward_days'] ?? 0),
                    'compensatory_days' => (float) ($data['compensatory_days'] ?? 0),
                    'notes' => $data['notes'] ?? null,
                    'updated_by_user_id' => $userId,
                ]
            );
        }
    }

    public function defaultOpeningDays(LeaveType $type): float
    {
        if ($type->is_accrued) {
            return 0.0;
        }

        if ($type->max_days_per_year !== null) {
            return (float) $type->max_days_per_year;
        }

        return (float) ($type->leave_days ?? 0);
    }

    /**
     * Staff with a current contract (status 1/2/7).
     *
     * @return list<int>
     */
    public function activeStaffIds(): array
    {
        return DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->distinct()
            ->orderBy('s.staff_id')
            ->pluck('s.staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{staff_processed: int, rows_created: int, rows_updated: int, rows_skipped: int, year: int}
     */
    public function bulkFillOpeningBalances(
        ?int $year = null,
        bool $overwrite = false,
        ?int $userId = null,
        ?array $leaveTypeIds = null,
    ): array {
        $year = $year ?? (int) now()->year;
        $types = LeaveType::query()
            ->where('is_active', true)
            ->when($leaveTypeIds, fn ($q) => $q->whereIn('leave_id', $leaveTypeIds))
            ->orderBy('sort_order')
            ->get();

        $staffIds = $this->activeStaffIds();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($staffIds as $staffId) {
            foreach ($types as $type) {
                $leaveId = (int) $type->leave_id;
                $existing = $this->openingRecord($staffId, $leaveId, $year);
                if ($existing && ! $overwrite) {
                    $skipped++;

                    continue;
                }

                $payload = [
                    'opening_days' => $this->defaultOpeningDays($type),
                    'carried_forward_days' => $overwrite && $existing
                        ? (float) $existing->carried_forward_days
                        : 0.0,
                    'compensatory_days' => $overwrite && $existing
                        ? (float) $existing->compensatory_days
                        : 0.0,
                    'notes' => $existing?->notes,
                    'updated_by_user_id' => $userId,
                ];

                if ($overwrite) {
                    $payload['opening_days'] = $this->defaultOpeningDays($type);
                }

                StaffLeaveOpeningBalance::query()->updateOrCreate(
                    [
                        'staff_id' => $staffId,
                        'leave_id' => $leaveId,
                        'calendar_year' => $year,
                    ],
                    $payload
                );

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        return [
            'staff_processed' => count($staffIds),
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_skipped' => $skipped,
            'year' => $year,
        ];
    }

    /**
     * Directory rows for leave balance administration.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{year: int, total: int}}
     */
    public function adminDirectory(string $search = '', ?int $year = null, int $page = 1, int $perPage = 25): array
    {
        $year = $year ?? (int) now()->year;
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $activeStaffSub = DB::table('staff_contracts')
            ->select('staff_id')
            ->whereIn('status_id', [1, 2, 7])
            ->groupBy('staff_id');

        $base = DB::table('staff as s')
            ->joinSub($activeStaffSub, 'active', 'active.staff_id', '=', 's.staff_id')
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function ($w) use ($term): void {
                    $w->where('s.fname', 'like', $term)
                        ->orWhere('s.lname', 'like', $term)
                        ->orWhere('s.oname', 'like', $term)
                        ->orWhere('s.work_email', 'like', $term)
                        ->orWhere('s.SAPNO', 'like', $term);
                });
            });

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select([
                's.staff_id',
                's.fname',
                's.lname',
                's.oname',
                's.work_email',
                DB::raw('s.SAPNO as sap_number'),
            ])
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->forPage($page, $perPage)
            ->get();

        $staffIds = $rows->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        $configuredCounts = [];
        if ($staffIds !== []) {
            $configuredCounts = StaffLeaveOpeningBalance::query()
                ->whereIn('staff_id', $staffIds)
                ->where('calendar_year', $year)
                ->selectRaw('staff_id, COUNT(*) as cnt')
                ->groupBy('staff_id')
                ->pluck('cnt', 'staff_id')
                ->all();
        }

        $activeTypeCount = LeaveType::query()->where('is_active', true)->count();

        $data = [];
        foreach ($rows as $row) {
            $sid = (int) $row->staff_id;
            $configured = (int) ($configuredCounts[$sid] ?? 0);
            $data[] = [
                'staff_id' => $sid,
                'name' => trim(implode(' ', array_filter([(string) $row->fname, (string) ($row->oname ?? ''), (string) $row->lname]))),
                'work_email' => $row->work_email,
                'sap_number' => $row->sap_number,
                'opening_types_configured' => $configured,
                'active_leave_types' => $activeTypeCount,
                'balances_complete' => $configured >= $activeTypeCount && $activeTypeCount > 0,
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'year' => $year,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }
}
