<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollWageType extends Model
{
    protected $table = 'payroll_wage_types';

    protected $fillable = [
        'code', 'name', 'category', 'calc_method', 'percent_base', 'default_amount',
        'taxable', 'pre_tax', 'is_system', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'taxable' => 'boolean',
        'pre_tax' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function staffItems(): HasMany
    {
        return $this->hasMany(PayrollStaffWageItem::class, 'wage_type_id');
    }
}