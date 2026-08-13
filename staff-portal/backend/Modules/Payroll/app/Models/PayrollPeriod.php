<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $table = 'payroll_periods';

    protected $fillable = ['year', 'month', 'label', 'status'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function fxRates(): HasMany
    {
        return $this->hasMany(PayrollFxRate::class, 'period_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'period_id');
    }
}