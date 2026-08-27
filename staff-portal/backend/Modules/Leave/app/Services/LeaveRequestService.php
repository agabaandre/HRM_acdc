<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Staff\Models\StaffContract;

class LeaveRequestService
{
    public function __construct(
        protected LeaveBalanceService $balances,
        protected LeavePolicyService $policy,
        protected ?HolidayCalendarService $holidays = null,
    ) {}

    public function minNoticeDays(): int
    {
        return max(0, (int) $this->policy->get('application_min_notice_days', 7));
    }

    public function earliestAllowedStartDate(): string
    {
        return now()->startOfDay()->addDays($this->minNoticeDays())->toDateString();
    }

    public function assertApplicationDates(string $start, string $end): void
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        if ($endDate->lt($startDate)) {
            throw new \InvalidArgumentException('End date must be on or after the start date.');
        }

        $earliest = Carbon::parse($this->earliestAllowedStartDate())->startOfDay();
        if ($startDate->lt($earliest)) {
            $days = $this->minNoticeDays();
            if ($days > 0) {
                throw new \InvalidArgumentException(
                    "Leave must start at least {$days} days from today. The earliest start date is {$earliest->toDateString()}."
                );
            }

            throw new \InvalidArgumentException('Leave start date cannot be in the past.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, ?UploadedFile $document = null): StaffLeave
    {
        $this->assertApplicationDates((string) $data['start_date'], (string) $data['end_date']);

        $staffId = (int) $data['staff_id'];
        $leaveTypeId = (int) $data['leave_id'];
        $requestedDays = (int) $data['requested_days'];

        $balance = $this->balances->snapshot($staffId, $leaveTypeId);
        if ($requestedDays > $balance['available']) {
            throw new \InvalidArgumentException('Requested days exceed available balance.');
        }

        $contract = StaffContract::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->first();

        $path = null;
        if ($document) {
            $path = $document->store('leave/'.date('Y'), 'public');
        }

        $workflow = app(LeaveApprovalWorkflowService::class);
        $hodId = (int) ($data['division_head'] ?? 0);
        if ($workflow->isEnabled() && $hodId < 1) {
            $hodId = (int) ($workflow->defaultHodForStaff($staffId)['staff_id'] ?? 0);
        }
        if ($workflow->isEnabled() && $hodId < 1) {
            throw new \InvalidArgumentException('Select a Head of Division for this leave request.');
        }

        $leave = StaffLeave::query()->create([
            'staff_id' => $staffId,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'leave_id' => $leaveTypeId,
            'email_leave' => $data['email_leave'] ?? '',
            'mobile_leave' => $data['mobile_leave'] ?? '',
            'supporting_staff' => $data['supporting_staff'] ?? '',
            'requested_days' => $requestedDays,
            'leave_balance' => (int) floor($balance['available'] - $requestedDays),
            'remarks' => $data['remarks'] ?? null,
            'contract_id' => $contract?->staff_contract_id ?? 0,
            'supervisor_id' => $contract?->first_supervisor ?? 0,
            'supervisor2_id' => $contract?->second_supervisor ?? 0,
            'division_head' => $hodId,
            'supporting_documentation' => $path,
            'approval_status' => 'Pending',
            'approval_status1' => 'Pending',
            'approval_status2' => 'Pending',
            'approval_status3' => 'Pending',
            'overall_status' => 'Pending',
        ]);

        if ($workflow->isEnabled()) {
            $workflow->snapshotForRequest($leave, $hodId);
            $workflow->recordSubmission($leave);
        }

        return $leave;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resubmit(int $requestId, int $staffId, array $data, ?UploadedFile $document = null): StaffLeave
    {
        $leave = StaffLeave::query()->findOrFail($requestId);
        if ((int) $leave->staff_id !== $staffId) {
            throw new \InvalidArgumentException('You can only revise your own leave request.');
        }
        if ((string) $leave->overall_status !== 'Returned') {
            throw new \InvalidArgumentException('Only returned leave requests can be revised and resubmitted.');
        }

        $this->assertApplicationDates((string) $data['start_date'], (string) $data['end_date']);

        $leaveTypeId = (int) $data['leave_id'];
        $requestedDays = (int) $data['requested_days'];
        $balance = $this->balances->snapshot($staffId, $leaveTypeId);
        if ($requestedDays > $balance['available']) {
            throw new \InvalidArgumentException('Requested days exceed available balance.');
        }

        if ($document) {
            $leave->supporting_documentation = $document->store('leave/'.date('Y'), 'public');
        }

        $workflow = app(LeaveApprovalWorkflowService::class);
        $hodId = (int) ($data['division_head'] ?? $leave->division_head ?? 0);
        if ($workflow->isEnabled() && $hodId < 1) {
            $hodId = (int) ($workflow->defaultHodForStaff($staffId)['staff_id'] ?? 0);
        }

        $leave->leave_id = $leaveTypeId;
        $leave->start_date = $data['start_date'];
        $leave->end_date = $data['end_date'];
        $leave->requested_days = $requestedDays;
        $leave->leave_balance = (int) floor($balance['available'] - $requestedDays);
        $leave->email_leave = $data['email_leave'] ?? $leave->email_leave;
        $leave->mobile_leave = $data['mobile_leave'] ?? $leave->mobile_leave;
        $leave->supporting_staff = (string) ($data['supporting_staff'] ?? $leave->supporting_staff);
        $leave->remarks = $data['remarks'] ?? $leave->remarks;
        $leave->division_head = $hodId;
        $leave->save();

        if ($workflow->isEnabled()) {
            $workflow->resetStepsForResubmit($leave, $hodId);
        } else {
            $leave->overall_status = 'Pending';
            $leave->save();
        }

        return $leave->fresh() ?? $leave;
    }

    public function approve(int $requestId, string $role, string $message): bool
    {
        $leave = StaffLeave::query()->findOrFail($requestId);
        $column = match ($role) {
            'supporting_staff' => 'approval_status',
            'hr' => 'approval_status1',
            'supervisor' => 'approval_status2',
            'hod' => 'approval_status3',
            default => null,
        };

        if ($column === null) {
            return false;
        }

        $wasOverall = (string) $leave->overall_status;
        $leave->{$column} = $message;
        $leave->updated_at = now();

        if ($role === 'hod') {
            $leave->overall_status = $message === 'Approved' ? 'Approved' : 'Rejected';
        }

        $saved = $leave->save();
        if ($saved && $wasOverall !== 'Approved' && $leave->overall_status === 'Approved') {
            $type = LeaveType::query()->find($leave->leave_id);
            $kind = $type?->compensatoryKind();
            if ($kind) {
                app(HolidayCompensatoryGrantService::class)->consume(
                    (int) $leave->staff_id,
                    $kind,
                    (float) $leave->requested_days,
                );
            }
        }

        return $saved;
    }

    public function workingDaysBetween(string $start, string $end, ?int $staffId = null, ?int $leaveTypeId = null): int
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $skipHolidays = $this->shouldSkipWeekdayHolidays($leaveTypeId);
        $holidayDates = $skipHolidays && $staffId
            ? $this->holidayDateMap($staffId, (int) $startDate->year, (int) $endDate->year)
            : [];

        $days = 0;
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            if ($skipHolidays && isset($holidayDates[$d->toDateString()])) {
                continue;
            }
            $days++;
        }

        return $days;
    }

    protected function shouldSkipWeekdayHolidays(?int $leaveTypeId): bool
    {
        $mode = (string) $this->policy->get('weekday_holiday_in_request', 'skip_all');
        if ($mode === 'count_all') {
            return false;
        }
        if ($mode === 'skip_annual_only') {
            if (! $leaveTypeId) {
                return false;
            }

            return (bool) LeaveType::query()->find($leaveTypeId)?->isAnnual();
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    protected function holidayDateMap(int $staffId, int $fromYear, int $toYear): array
    {
        $calendar = $this->holidays ?? app(HolidayCalendarService::class);
        $dates = [];
        for ($year = $fromYear; $year <= $toYear; $year++) {
            foreach ($calendar->holidayDatesForStaff($staffId, $year) as $date) {
                $dates[$date] = true;
            }
        }

        return $dates;
    }
}
