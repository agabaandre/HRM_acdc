<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approver extends Model
{
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
        'workflow_dfn_id', 'staff_id', 'oic_staff_id', 'start_date', 'end_date'
    ];

    /**
     * Get the workflow definition that owns the approver.
     */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_dfn_id');
    }
}
