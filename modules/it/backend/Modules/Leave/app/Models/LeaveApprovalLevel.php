<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class LeaveApprovalLevel extends Model
{
    protected $table = 'leave_approval_levels';

    protected $fillable = [
        'sort_order',
        'role',
        'staff_id',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'staff_id' => 'integer',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
