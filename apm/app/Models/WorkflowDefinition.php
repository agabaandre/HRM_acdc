<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'workflow_definition';

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
        'role', 'workflow_id', 'approval_order', 'is_enabled'
    ];

    /**
     * Get the workflow that owns the workflow definition.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    /**
     * Get the approval conditions for the workflow definition.
     */
    public function approvalConditions(): HasMany
    {
        return $this->hasMany(ApprovalCondition::class, 'workflow_definition_id');
    }

    /**
     * Get the approvers for the workflow definition.
     */
    public function approvers(): HasMany
    {
        return $this->hasMany(Approver::class, 'workflow_dfn_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Approver::class, 'approval_order');
    }
}
