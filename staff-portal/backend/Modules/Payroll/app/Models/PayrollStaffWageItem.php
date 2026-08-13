<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollStaffWageItem extends Model
{
    protected $table = 'payroll_staff_wage_items';

    protected $fillable = [
        'staff_id', 'wage_type_id', 'amount', 'percent', 'currency',
        'start_date', 'end_date', 'is_active',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'amount' => 'decimal:2',
        'percent' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(PayrollWageType::class, 'wage_type_id');
    }
}