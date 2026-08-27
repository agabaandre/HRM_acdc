<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class StaffLeaveApprovalTrail extends Model
{
    protected $table = 'staff_leave_approval_trail';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'staff_id',
        'step_id',
        'role',
        'label',
        'action',
        'comments',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_id' => 'integer',
            'staff_id' => 'integer',
            'step_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(StaffLeave::class, 'request_id', 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
