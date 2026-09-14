<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class StaffLeaveOpeningBalance extends Model
{
    protected $fillable = [
        'staff_id',
        'leave_id',
        'calendar_year',
        'opening_days',
        'carried_forward_days',
        'compensatory_days',
        'notes',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'opening_days' => 'float',
            'carried_forward_days' => 'float',
            'compensatory_days' => 'float',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_id', 'leave_id');
    }
}
