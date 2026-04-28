<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use function React\Promise\Stream\first;
use App\Models\Activity;
use App\Models\Staff;
use Illuminate\Support\Collection;
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
        'previous_overall_status',
        'approval_order_map',
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

        if ( $definitions->count() > 1 && $definitions[0]->category ) {
            // Multiple definitions at this level - use category-based routing
            $categoryDefinition = $definitions->where('category', $this->division->category)->first();
            
            if ($categoryDefinition) {
                return $categoryDefinition;
            }
            
            // If no definition found at current level for this category, 
            // look for the next available level for this category
            $nextDefinition = WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)
                ->where('approval_order', '>', $this->approval_level)
                ->where('is_enabled', 1)
                ->where('category', $this->division->category)
                ->orderBy('approval_order', 'asc')
                ->first();
                
            if ($nextDefinition) {
                return $nextDefinition;
            }
        }

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
        return $this->hasMany(ApprovalTrail::class, 'model_id')
            ->where('model_type', 'App\Models\Matrix')
            ->orderByRaw('CASE WHEN approval_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('approval_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    public function participant_schedules(){
        return $this->hasMany(ParticipantSchedule::class);
    }

    /**
     * Build travel days per staff from activities' internal_participants JSON (like staff-quarterly-travel).
     * Uses effective internal_participants (approved change request overrides activity).
     * Only includes activities where overall_status != 'cancelled'.
     * Only counts participant_days where international_travel is 1 (travel days); excludes domestic/non-travel (international_travel=0) so division schedule matches staff drill-down and staff-quarterly-travel report.
     * Returns array keyed by staff_id (int) with keys division_days, other_days (ints).
     */
    public function getTravelDaysFromInternalParticipants(): array
    {
        $activities = $this->activities()
            ->where('overall_status', '!=', 'cancelled')
            ->get();

        $byStaff = [];
        $divisionId = (int) $this->division_id;
        $allStaffIds = [];

        foreach ($activities as $activity) {
            $participants = $activity->getEffectiveInternalParticipants(true); // only count days where international_travel=1
            foreach (array_keys($participants) as $staffIdStr) {
                $staffId = (int) $staffIdStr;
                if ($staffId > 0) {
                    $allStaffIds[$staffId] = true;
                }
            }
        }

        $staffById = !empty($allStaffIds)
            ? Staff::whereIn('staff_id', array_keys($allStaffIds))->get()->keyBy('staff_id')
            : collect();

        foreach ($activities as $activity) {
            $participants = $activity->getEffectiveInternalParticipants(true); // only count days where international_travel=1
            foreach ($participants as $staffIdStr => $days) {
                $staffId = (int) $staffIdStr;
                if ($staffId <= 0 || $days <= 0) {
                    continue;
                }
                if (!isset($byStaff[$staffId])) {
                    $byStaff[$staffId] = ['division_days' => 0, 'other_days' => 0];
                }
                $staff = $staffById->get($staffId);
                $staffDivisionId = $staff ? (int) $staff->division_id : null;
                if ($staffDivisionId === $divisionId) {
                    $byStaff[$staffId]['division_days'] += $days;
                } else {
                    $byStaff[$staffId]['other_days'] += $days;
                }
            }
        }

        return $byStaff;
    }

    public function getDivisionStaffAttribute(){
        $division_id = $this->division_id;
        $travelMap = $this->getTravelDaysFromInternalParticipants();

        $staff = Staff::where('division_id', $division_id)
            ->whereNotIn('status', ['Expired', 'Separated'])
            ->orderBy('fname', 'asc')
            ->get();

        foreach ($staff as $s) {
            $sid = (int) $s->staff_id;
            $d = $travelMap[$sid] ?? ['division_days' => 0, 'other_days' => 0];
            $s->division_days = $d['division_days'];
            $s->other_days = $d['other_days'];
        }

        return $staff;
    }


    
    public function getDivisionScheduleAttribute(){
        $travelMap = $this->getTravelDaysFromInternalParticipants();
        $divisionId = (int) $this->division_id;
        $quarter = $this->quarter;
        $year = (int) $this->year;

        $staffIds = array_keys($travelMap);
        $staffById = $staffIds ? Staff::whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();

        $rows = [];
        foreach ($travelMap as $participantId => $days) {
            $total = $days['division_days'] + $days['other_days'];
            if ($total <= 0) {
                continue;
            }
            $staff = $staffById->get($participantId);
            $isHomeDivision = $staff ? ((int) $staff->division_id === $divisionId) : false;
            $rows[] = (object) [
                'id' => null,
                'participant_id' => $participantId,
                'quarter' => $quarter,
                'year' => $year,
                'participant_days' => $total,
                'is_home_division' => $isHomeDivision ? 1 : 0,
                'division_id' => $divisionId,
                'staff' => $staff,
            ];
        }

        return new Collection($rows);
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