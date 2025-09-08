<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Workflow extends Model
{
    use Auditable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'workflows';

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
        'workflow_name', 'Description', 'is_active'
    ];

    /**
     * Get the workflow definitions for the workflow.
     */
    public function workflowDefinitions(): HasMany
    {
        return $this->hasMany(WorkflowDefinition::class, 'workflow_id');
    }

    /**
     * Get the approval conditions for the workflow.
     */
    public function approvalConditions(): HasMany
    {
        return $this->hasMany(ApprovalCondition::class, 'workflow_id');
    }
}
