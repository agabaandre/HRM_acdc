<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLoan extends Model
{
    protected $table = 'payroll_loans';

    protected $fillable = [
        'staff_id', 'type', 'currency', 'principal', 'interest_rate',
        'installment_amount', 'installment_count', 'status',
        'requested_by_user_id', 'supervisor_id', 'approved_by_user_id',
        'rejected_reason', 'disbursed_at', 'start_period_id', 'wage_type_id', 'notes',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'installment_amount' => 'decimal:2',
        'installment_count' => 'integer',
        'disbursed_at' => 'datetime',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(PayrollLoanSchedule::class, 'loan_id')->orderBy('sequence');
    }

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(PayrollWageType::class, 'wage_type_id');
    }

    public function startPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'start_period_id');
    }
}