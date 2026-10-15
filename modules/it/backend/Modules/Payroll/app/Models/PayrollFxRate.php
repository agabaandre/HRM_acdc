<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollFxRate extends Model
{
    protected $table = 'payroll_fx_rates';

    protected $fillable = ['period_id', 'currency', 'rate_to_default'];

    protected $casts = [
        'rate_to_default' => 'decimal:8',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }
}