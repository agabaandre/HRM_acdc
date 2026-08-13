<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\Staff;

class StaffLeave extends Model
{
    protected $table = 'staff_leave';

    protected $primaryKey = 'request_id';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'staff_id',
        'start_date',
        'end_date',
        'leave_id',
        'email_leave',
        'mobile_leave',
        'supporting_staff',
        'requested_days',
        'leave_balance',
        'remarks',
        'contract_id',
        'supervisor_id',
        'supervisor2_id',
        'division_head',
        'reject_reason',
        'supporting_documentation',
        'approval_status',
        'approval_status1',
        'approval_status2',
        'approval_status3',
        'overall_status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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
