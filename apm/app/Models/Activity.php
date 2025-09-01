<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use Illuminate\Support\Str;

class Activity extends Model
{
    use HasFactory, HasApprovalWorkflow;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'returned';
    const STATUS_OK = 'passed';

    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
        'workplan_activity_code',
        'activity_ref',
        'matrix_id',
        'staff_id',
        'responsible_person_id',
        'date_from',
        'date_to',
        'total_participants',
        'total_external_participants',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_special_memo',
        'status',
        'fund_type_id',
        'division_id',

        // JSON fields
        'location_id',
        'internal_participants',
        'budget_id',
        'budget',
        'attachment',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['formatted_dates','status','my_last_action'];

    protected $casts = [
        'id' => 'integer',
        'forward_workflow_id' => 'integer',
        'reverse_workflow_id' => 'integer',
        'matrix_id' => 'integer',
        'staff_id' => 'integer',
        'responsible_person_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'division_id' => 'integer',
        'is_special_memo' => 'boolean',
        'date_from' => 'date',
        'date_to' => 'date',
        'total_participants' => 'integer',
        'total_external_participants' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // JSON
        'location_id' => 'array',
        'internal_participants' => 'array',
        'budget_id' => 'array',
        'budget' => 'array',
        'attachment' => 'array',
    ];

    // --- Relationships ---

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class, 'activity_id');
    }

    // JSON-BASED: location_id[] mapped to Location model
    public function getLocationsAttribute()
    {
        return Location::whereIn('id', $this->location_id ?? [])->get();
    }

    // JSON-BASED: budget_id[] mapped to FundCode model
    public function getFundCodesAttribute()
    {
        return FundCode::whereIn('id', $this->budget_id ?? [])->get();
    }

    // JSON-BASED: internal_participants[] mapped to Staff
    public function getInternalParticipantsDetailsAttribute()
    {
        return Staff::whereIn('staff_id', $this->internal_participants ?? [])->get();
    }


    // --- Accessors & Utility ---

    public function getFormattedDatesAttribute(): string
    {
        return ($this->date_from)?$this->date_from->format('M j, Y') . ' - ' . $this->date_to->format('M j, Y'):'';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (empty($activity->activity_ref)) {
                $activity->activity_ref = $activity->generateActivityRef();
            }
        });
    }

    protected function generateActivityRef(): string
    {
        $division_name = user_session('division_name');
        $short = ucwords($this->generateShortCodeFromDivision($division_name));
        $prefix = 'AU/CDC/' . $short . '/QM';
        $quarter =  $this->matrix->quarter;
        $year = substr($this->matrix->year, -2);
    
        $latestActivity = self::where('matrix_id', $this->matrix_id)
            ->where('activity_ref', 'like', "{$prefix}/{$quarter}/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();
    
        $sequence = $latestActivity
            ? ((int) last(explode('/', $latestActivity->workplan_activity_code)) + 1)
            : 1;
    
        return "{$prefix}/{$quarter}/{$year}/" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate short code from division name by removing joining words and using initials
     */
    protected function generateShortCodeFromDivision(string $name): string
    {
        $ignore = ['of', 'and', 'for', 'the', 'in'];
        $words = preg_split('/\s+/', strtolower($name));
        $initials = array_map(function ($word) use ($ignore) {
            return in_array($word, $ignore) ? '' : strtoupper($word[0]);
        }, $words);
    
        return implode('', array_filter($initials));
    }

    public function getBudgetAttribute($value)
    {
        return json_decode($value); // or false for object
    }

    public function getStatusAttribute(){
        $user = session('user', []);
        $matrix = $this->matrix;

        if($matrix->staff_id == $user['staff_id'] ){
         return ($matrix->forward_workflow_id==null)?'Draft':(($matrix->overall_status =='approved')?'Passed':'Pending');
        }

        $last_log = ActivityApprovalTrail::where('activity_id',$this->id)->orderBy('id','asc')->first();

        if($last_log)
         return strtoupper($last_log->action);

         if(can_take_action($matrix))
         return ' Pending';
    }

    public function getMyLastActionAttribute(){
        $user = session('user', []);
        return ActivityApprovalTrail::where('activity_id',$this->id)
        ->where('staff_id',$user['staff_id'])
        ->where('approval_order',$this->matrix->approval_level)
        ->orderByDesc('id')->first();
    }

    public function getFinalApprovalStatusAttribute(){
        // Get the latest approval trail entry for this activity
        $latestTrail = ActivityApprovalTrail::where('activity_id', $this->id)
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$latestTrail) {
            return 'pending'; // No approval trail yet
        }
        
        // Check if the latest action was 'passed' or 'failed'
        if (strtolower($latestTrail->action) === 'passed') {
            return 'passed';
        } elseif (strtolower($latestTrail->action) === 'failed') {
            return 'failed';
        } else {
            return 'pending'; // Other actions like 'returned', 'rejected', etc.
        }
    }

    public function focalPerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    public function activity_budget(){
        return $this->hasMany(ActivityBudget::class);
    }

    public function participantSchedules(): HasMany
    {
        return $this->hasMany(ParticipantSchedule::class, 'activity_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
