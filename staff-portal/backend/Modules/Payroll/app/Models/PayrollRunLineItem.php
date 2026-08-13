<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunLineItem extends Model
{
    protected $table = 'payroll_run_line_items';

    protected $fillable = [
        'run_line_id', 'wage_type_id', 'category', 'amount', 'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class, 'run_line_id');
    }

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(PayrollWageType::class, 'wage_type_id');
    }
}