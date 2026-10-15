<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollTaxRule extends Model
{
    protected $table = 'payroll_tax_rules';

    protected $fillable = [
        'code', 'name', 'jurisdiction_code', 'effective_from', 'effective_to',
        'applies_to', 'wage_type_id', 'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(PayrollTaxBand::class, 'tax_rule_id')->orderBy('sort_order')->orderBy('from_amount');
    }

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(PayrollWageType::class, 'wage_type_id');
    }
}