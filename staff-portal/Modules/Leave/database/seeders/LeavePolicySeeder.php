<?php

namespace Modules\Leave\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeavePolicyService;

class LeavePolicySeeder extends Seeder
{
    public function run(): void
    {
        app(LeavePolicyService::class)->save(app(LeavePolicyService::class)->defaults());

        $types = [
            ['code' => 'ANNUAL', 'leave_name' => 'Annual Leave (Home Leave)', 'leave_days' => 28, 'is_accrued' => 1, 'accrual_rate' => 2.33, 'min_days_per_year' => 15, 'max_days_per_year' => 45, 'requires_hr_approval' => true, 'deduct_compensatory_first' => true, 'sort_order' => 1],
            ['code' => 'SICK', 'leave_name' => 'Sick Leave', 'leave_days' => 0, 'is_accrued' => 0, 'accrual_rate' => 0, 'requires_medical_certificate' => true, 'medical_report_after_days' => 10, 'sort_order' => 2],
            ['code' => 'MATERNITY', 'leave_name' => 'Maternity Leave', 'leave_days' => 98, 'is_accrued' => 0, 'max_instances' => 4, 'sort_order' => 3],
            ['code' => 'PATERNITY', 'leave_name' => 'Paternity Leave', 'leave_days' => 10, 'is_accrued' => 0, 'max_instances' => 4, 'sort_order' => 4],
            ['code' => 'STUDY', 'leave_name' => 'Study Leave', 'leave_days' => 0, 'is_accrued' => 0, 'sort_order' => 5],
            ['code' => 'COMPENSATORY', 'leave_name' => 'Compensatory Leave', 'leave_days' => 0, 'is_accrued' => 0, 'sort_order' => 6],
            ['code' => 'SPECIAL', 'leave_name' => 'Special Leave', 'leave_days' => 0, 'is_accrued' => 0, 'sort_order' => 7],
        ];

        foreach ($types as $row) {
            $existing = LeaveType::query()->where('code', $row['code'])->first()
                ?? LeaveType::query()->where('leave_name', $row['leave_name'])->first();

            $payload = array_merge([
                'accrual_rate' => (float) ($row['accrual_rate'] ?? 0),
                'is_active' => true,
            ], $row);

            if ($existing) {
                $existing->update($payload);
            } else {
                LeaveType::query()->create($payload);
            }
        }
    }
}
