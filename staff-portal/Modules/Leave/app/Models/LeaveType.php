<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $table = 'leave_types';

    protected $primaryKey = 'leave_id';

    public $timestamps = false;

    protected $fillable = [
        'leave_name',
        'code',
        'leave_days',
        'is_accrued',
        'accrual_rate',
        'is_active',
        'requires_hr_approval',
        'requires_medical_certificate',
        'medical_report_after_days',
        'max_instances',
        'max_days_per_year',
        'min_days_per_year',
        'deduct_compensatory_first',
        'policy_notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_accrued' => 'boolean',
            'accrual_rate' => 'float',
            'is_active' => 'boolean',
            'requires_hr_approval' => 'boolean',
            'requires_medical_certificate' => 'boolean',
            'max_days_per_year' => 'float',
            'min_days_per_year' => 'float',
            'deduct_compensatory_first' => 'boolean',
        ];
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(StaffLeaveOpeningBalance::class, 'leave_id', 'leave_id');
    }

    public function isAnnual(): bool
    {
        $code = strtoupper((string) $this->code);
        $name = strtolower((string) $this->leave_name);

        return $code === 'ANNUAL' || str_contains($name, 'annual') || str_contains($name, 'home');
    }
}
