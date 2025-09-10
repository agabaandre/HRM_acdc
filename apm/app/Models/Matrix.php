<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use function React\Promise\Stream\first;
use App\Models\Staff;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Matrix extends Model
{
    use HasFactory, HasApprovalWorkflow, Auditable;

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
     * The validation rules for creating/updating a matrix.
     *
     * @var array
     */
    public static $rules = [
        'division_id' => 'required|exists:divisions,id',
        'year' => 'required|integer|min:2020|max:2030',
        'quarter' => 'required|in:Q1,Q2,Q3,Q4',
        'key_result_area' => 'required|array',
        'focal_person_id' => 'required|exists:staff,staff_id',
    ];

    /**
     * Check if a matrix already exists for the given division, year, and quarter.
     *
     * @param int $divisionId
     * @param int $year
     * @param string $quarter
     * @param int|null $excludeId
     * @return bool
     */
    public static function existsForDivisionYearQuarter($divisionId, $year, $quarter, $excludeId = null)
    {
        $query = static::where('division_id', $divisionId)
                       ->where('year', $year)
                       ->where('quarter', $quarter);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * Get existing matrices for a division to show what's already created.
     *
     * @param int $divisionId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getExistingMatricesForDivision($divisionId)
    {
        return static::where('division_id', $divisionId)
                    ->orderBy('year', 'desc')
                    ->orderBy('quarter', 'desc')
                    ->get(['id', 'year', 'quarter', 'overall_status', 'created_at']);
    }

    /**
     * Get the next available quarter for a division in a given year.
     *
     * @param int $divisionId
     * @param int $year
     * @return string|null
     */
    public static function getNextAvailableQuarter($divisionId, $year)
    {
        $existingQuarters = static::where('division_id', $divisionId)
                                 ->where('year', $year)
                                 ->pluck('quarter')
                                 ->toArray();
        
        $allQuarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $availableQuarters = array_diff($allQuarters, $existingQuarters);
        
        return !empty($availableQuarters) ? reset($availableQuarters) : null;
    }

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
        return $this->hasMany(ApprovalTrail::class, 'model_id')->where('model_type', 'App\Models\Matrix');
    }

    public function participant_schedules(){
        return $this->hasMany(ParticipantSchedule::class);
    }

    public function getDivisionStaffAttribute(){
        // Use the matrix's division_id instead of the logged-in user's division_id
        $division_id = $this->division_id;
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

    public function getResourceUrlAttribute()
    {
        return route('matrices.show', $this->id);
    }

}