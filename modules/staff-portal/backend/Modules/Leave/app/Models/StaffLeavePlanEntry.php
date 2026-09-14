<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeavePlanEntry extends Model
{
    protected $table = 'staff_leave_plan_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'leave_plan_id' => 'integer',
            'leave_id' => 'integer',
            'planned_days' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StaffLeavePlan::class, 'leave_plan_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_id', 'leave_id');
    }
}
