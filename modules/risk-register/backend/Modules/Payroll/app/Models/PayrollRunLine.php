<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRunLine extends Model
{
    protected $table = 'payroll_run_lines';

    protected $fillable = [
        'run_id', 'staff_id', 'currency', 'basic', 'gross', 'taxable', 'tax',
        'deductions', 'benefits', 'net', 'fx_rate_to_default', 'net_default',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'basic' => 'decimal:2',
        'gross' => 'decimal:2',
        'taxable' => 'decimal:2',
        'tax' => 'decimal:2',
        'deductions' => 'decimal:2',
        'benefits' => 'decimal:2',
        'net' => 'decimal:2',
        'fx_rate_to_default' => 'decimal:8',
        'net_default' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunLineItem::class, 'run_line_id');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(PayrollPayslip::class, 'run_line_id');
    }
}