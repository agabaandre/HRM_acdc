<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollStaffPay extends Model
{
    protected $table = 'payroll_staff_pay';

    protected $fillable = [
        'staff_id',
        'staff_contract_id',
        'currency',
        'basic_salary',
        'bank_name',
        'bank_account',
        'bank_branch',
        'tax_identifier',
        'pay_status',
        'notes',
        'inherited_unverified',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'staff_contract_id' => 'integer',
        'basic_salary' => 'decimal:2',
        'inherited_unverified' => 'boolean',
    ];

    public function wageItems(): HasMany
    {
        return $this->hasMany(PayrollStaffWageItem::class, 'staff_id', 'staff_id');
    }
}
