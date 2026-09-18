<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class StaffLeaveApprovalStep extends Model
{
    protected $table = 'staff_leave_approval_steps';

    protected $fillable = [
        'request_id',
        'sort_order',
        'role',
        'staff_id',
        'label',
        'status',
        'comments',
        'acted_at',
        'acted_by',
    ];

    protected function casts(): array
    {
        return [
            'request_id' => 'integer',
            'sort_order' => 'integer',
            'staff_id' => 'integer',
            'acted_at' => 'datetime',
            'acted_by' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(StaffLeave::class, 'request_id', 'request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
