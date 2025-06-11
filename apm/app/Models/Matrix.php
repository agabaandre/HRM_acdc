<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matrix extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'focal_person_id',
        'division_id',
        'year',
        'quarter',
        'key_result_area',
        'staff_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'approval_level',
        'next_approval_level',
        'overall_status',
    ];

    protected $appends =['workflow_definition','has_intramural','has_extramural'];

    /**
     * Get the casts for the model.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'focal_person_id' => 'integer',
            'division_id' => 'integer',
            'key_result_area' => 'array',
            'staff_id' => 'integer',
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'approval_level' => 'integer',
            'next_approval_level' => 'integer',
        ];
    }
    
    /**
     * Set the key_result_area attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setKeyResultAreaAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['key_result_area'] = json_encode($value);
        } else {
            $this->attributes['key_result_area'] = $value;
        }
    }

    public function division()
    {
        return $this->belongsTo(Division::class,"division_id","division_id");
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"staff_id","staff_id");
    }

    public function focalPerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'focal_person_id', 'staff_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id', 'id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id', 'id');
    }



    public function getWorkflowDefinitionAttribute()
    {
        $definitions = WorkflowDefinition::where('approval_order', $this->approval_level)
            ->where('workflow_id', $this->forward_workflow_id)
            ->where('is_enabled',1)
            ->get();

        if ( $definitions->count() > 1 && $definitions[0]->category )
                return $definitions->where('category', $this->division->category)->first();

          return ($definitions->count()>0)?$definitions[0]:WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)->where('approval_order', 1)->first();
    }

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }

    public function matrixApprovalTrails(){
        return $this->hasMany(MatrixApprovalTrail::class);
    }

    public function getHasIntramuralAttribute(): bool
    {
        return $this->activities()->where('fund_type_id', 1)->exists();
    }

    public function getHasExtramuralAttribute(): bool
    {
        return $this->activities()->where('fund_type_id', 2)->exists();
    }
}