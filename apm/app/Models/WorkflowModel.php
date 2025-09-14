<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowModel extends Model
{
    protected $fillable = [
        'model_name',
        'workflow_id',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the workflow that owns the model assignment.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get workflow ID for a specific model.
     */
    public static function getWorkflowIdForModel(string $modelName): ?int
    {
        $assignment = self::active()
            ->where('model_name', $modelName)
            ->first();
            
        return $assignment ? $assignment->workflow_id : null;
    }

    /**
     * Set workflow ID for a specific model.
     */
    public static function setWorkflowIdForModel(string $modelName, int $workflowId, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['model_name' => $modelName],
            [
                'workflow_id' => $workflowId,
                'is_active' => true,
                'description' => $description
            ]
        );
    }
}
