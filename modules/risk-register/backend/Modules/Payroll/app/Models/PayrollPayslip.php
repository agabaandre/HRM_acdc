<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayslip extends Model
{
    protected $table = 'payroll_payslips';

    protected $fillable = [
        'run_line_id', 'staff_id', 'period_id', 'run_id', 'pdf_path', 'ytd', 'generated_at', 'emailed_at',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'ytd' => 'array',
        'generated_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class, 'run_line_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }
}