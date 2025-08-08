<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use function React\Promise\Stream\first;
use App\Models\Staff;

class Matrix extends Model
{
    use HasFactory, HasApprovalWorkflow;

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

    protected $appends =['workflow_definition','has_intramural','has_extramural','current_actor','division_schedule','division_staff',"intramural_budget","extramural_budget"];

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
        return $this->belongsTo(Division::class,"division_id");
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

    public function getCurrentActorAttribute()
    {
        if($this->overall_status =='approved')
        return null;

        $role = $this->workflow_definition;
        $staff_id = $this->focal_person_id;

        if($role){

        if($role->is_division_specific)
         $staff_id =  $this->division->{$role->division_reference_column};
        else
         $staff_id =Approver::select('staff_id')
        ->where('workflow_dfn_id',$role->id)->first()->staff_id;
      }
       
      return Staff::select('lname','fname','staff_id')->where('staff_id',$staff_id)->first();

    }

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }

    public function matrixApprovalTrails(){
        return $this->hasMany(ApprovalTrail::class, 'model_id')->where('model_type', Matrix::class);
    }

    public function getHasIntramuralAttribute(): bool
    {
        return $this->activities()->where('fund_type_id', 1)->exists();
    }

    public function getHasExtramuralAttribute(): bool
    {
        return $this->activities()->where('fund_type_id', 2)->exists();
    }

    public function participant_schedules(){
        return $this->hasMany(ParticipantSchedule::class);
    }

    public function getDivisionStaffAttribute(){
        $division_id = user_session()['division_id'];
        //Get staff with with the division days in this quater and year
        return Staff::where('division_id', $division_id)
        ->withSum([
            'participant_schedules as division_days' => function ($query) {
                $query->where('quarter', $this->quarter)
                      ->where('year', $this->year)
                      ->where('is_home_division', 1);
            }
        ], 'participant_days')
        ->withSum([
            'participant_schedules as other_days' => function ($query) {
                $query->where('quarter', $this->quarter)
                      ->where('year', $this->year)
                      ->where('is_home_division', 0);
            }
        ], 'participant_days')
        ->orderBy('fname','asc')
        ->get();
    }


    
    public function getDivisionScheduleAttribute(){
        return   $this->participant_schedules()->selectRaw('
        MAX(id) as id,
        participant_id,
        MAX(quarter) as quarter,
        MAX(year) as year,
        MAX(participant_days) as participant_days,
        MAX(is_home_division) as is_home_division,
        MAX(division_id) as division_id
        ')
        ->with('staff')
        ->where('division_id', $this->division_id)
        ->where('quarter', $this->quarter)
        ->where('year', $this->year)
        ->groupBy('participant_id')
        ->get();
    }

    public function getIntramuralBudgetAttribute(){
        //sum ActivityBudget.total where ActivityBudget->fund_code->fundType->id in (1,3)
        return $this->activities()
            ->with(['activity_budget.fundcode.fundType'])
            ->get()
            ->flatMap(function($activity) {
                return $activity->activity_budget;
            })
            ->filter(function($budget) {
                return $budget->fundcode && $budget->fundcode->fundType && in_array($budget->fundcode->fundType->id, [1, 3]);
            })
            ->sum('total');
    }

    public function getExtramuralBudgetAttribute(){
        //sum ActivityBudget.total where ActivityBudget->fund_code->fundType->id in (1,3)
        return $this->activities()
            ->with(['activity_budget.fundcode.fundType'])
            ->get()
            ->flatMap(function($activity) {
                return $activity->activity_budget;
            })
            ->filter(function($budget) {
                return $budget->fundcode && $budget->fundcode->fundType && $budget->fundcode->fundType->id==2;
            })
            ->sum('total');
    }

}