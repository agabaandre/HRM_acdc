<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'activity_id',
        'workflow_id',
        'reverse_workflow_id',
        'approval_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'activity_id' => 'integer',
            'workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(ReverseWorkflow::class);
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }
}
