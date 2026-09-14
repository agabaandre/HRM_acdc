<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLoanSchedule extends Model
{
    protected $table = 'payroll_loan_schedules';

    protected $fillable = [
        'loan_id', 'sequence', 'due_period_id', 'amount', 'status', 'run_line_item_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(PayrollLoan::class, 'loan_id');
    }

    public function duePeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'due_period_id');
    }
}