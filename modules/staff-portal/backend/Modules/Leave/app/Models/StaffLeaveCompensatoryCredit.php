<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class StaffLeaveCompensatoryCredit extends Model
{
    protected $fillable = [
        'staff_id',
        'kind',
        'days',
        'days_used',
        'reason',
        'granted_on',
        'expires_on',
        'granted_by_user_id',
        'source_holiday_rule_id',
        'source_date',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'float',
            'days_used' => 'float',
            'granted_on' => 'date',
            'expires_on' => 'date',
            'source_date' => 'date',
            'source_holiday_rule_id' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function remainingDays(): float
    {
        return max(0, (float) $this->days - (float) $this->days_used);
    }
}
