<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $table = 'payroll_runs';

    protected $fillable = [
        'period_id', 'status', 'off_cycle', 'title', 'notes',
        'simulated_at', 'posted_at', 'posted_by_user_id',
        'staff_count', 'total_gross_default', 'total_net_default',
    ];

    protected $casts = [
        'off_cycle' => 'boolean',
        'simulated_at' => 'datetime',
        'posted_at' => 'datetime',
        'staff_count' => 'integer',
        'total_gross_default' => 'decimal:2',
        'total_net_default' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class, 'run_id');
    }
}