<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Approver extends Model
{
    use Auditable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'approvers';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'workflow_dfn_id', 'staff_id', 'oic_staff_id', 'admin_assistant', 'start_date', 'end_date'
    ];

    /**
     * Get the workflow definition that owns the approver.
     */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_dfn_id');
    }

    /**
     * Get the staff member assigned as approver.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the staff member assigned as Officer In Charge (OIC).
     */
    public function oicStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'oic_staff_id', 'staff_id');
    }

    /**
     * Get the admin assistant assigned to the approver.
     */
    public function adminAssistant(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'admin_assistant', 'staff_id');
    }
}
