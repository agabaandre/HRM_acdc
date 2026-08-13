<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTaxBand extends Model
{
    protected $table = 'payroll_tax_bands';

    protected $fillable = [
        'tax_rule_id', 'from_amount', 'to_amount', 'rate_percent', 'fixed_amount', 'sort_order',
    ];

    protected $casts = [
        'from_amount' => 'decimal:2',
        'to_amount' => 'decimal:2',
        'rate_percent' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PayrollTaxRule::class, 'tax_rule_id');
    }
}