<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use Illuminate\Support\Str;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Activity extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable;

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
        'document_number',
        'approval_level',
        'next_approval_level',

        // JSON fields
        'location_id',
        'internal_participants',
        'budget_id',
        'budget_breakdown',
        'attachment',
        'is_single_memo',
        'approval_level',
        'overall_status',
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
        'approval_level' => 'integer',
        'next_approval_level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // JSON
        'location_id' => 'array',
        'internal_participants' => 'array',
        'budget_id' => 'array',
        'budget_breakdown' => 'array',
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

    public function locations()
    {
        $locationIds = $this->location_id ?? [];
        
        // Handle both array and JSON string formats
        if (is_string($locationIds)) {
            $locationIds = json_decode($locationIds, true) ?? [];
        }
        
        // Ensure it's an array and not empty
        if (!is_array($locationIds) || empty($locationIds)) {
            return collect();
        }
        
        return Location::whereIn('id', $locationIds)->get();
    }

    // JSON-BASED: location_id[] mapped to Location model
    public function getLocationsAttribute()
    {
        $locationIds = $this->location_id ?? [];
        
        // Handle both array and JSON string formats
        if (is_string($locationIds)) {
            $locationIds = json_decode($locationIds, true) ?? [];
        }
        
        // Ensure it's an array and not empty
        if (!is_array($locationIds) || empty($locationIds)) {
            return collect();
        }
        
        return Location::whereIn('id', $locationIds)->get();
    }

    // JSON-BASED: budget_id[] mapped to FundCode model
    public function getFundCodesAttribute()
    {
        $budgetIds = $this->budget_id ?? [];
        
        // Handle both array and JSON string formats
        if (is_string($budgetIds)) {
            $budgetIds = json_decode($budgetIds, true) ?? [];
        }
        
        // Ensure it's an array and not empty
        if (!is_array($budgetIds) || empty($budgetIds)) {
            return collect();
        }
        
        return FundCode::whereIn('id', $budgetIds)->get();
    }

    // JSON-BASED: internal_participants[] mapped to Staff
    public function getInternalParticipantsDetailsAttribute()
    {
        $participantIds = $this->internal_participants ?? [];
        
        // Handle both array and JSON string formats
        if (is_string($participantIds)) {
            $participantIds = json_decode($participantIds, true) ?? [];
        }
        
        // Ensure it's an array and not empty
        if (!is_array($participantIds) || empty($participantIds)) {
            return collect();
        }
        
        // Recursively flatten and extract IDs
        $flatIds = $this->flattenParticipantIds($participantIds);
        
        if (empty($flatIds)) {
            return collect();
        }
        
        return Staff::whereIn('staff_id', $flatIds)->get();
    }
    
    /**
     * Recursively flatten participant IDs from nested arrays
     */
    private function flattenParticipantIds($data)
    {
        $ids = [];
        
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item)) {
                    // Look for staff_id or id keys within the item
                    if (isset($item['staff_id'])) {
                        $ids[] = $item['staff_id'];
                    } elseif (isset($item['id'])) {
                        $ids[] = $item['id'];
                    } else {
                        // If no staff_id or id found, check if the key itself is a participant ID
                        if (is_numeric($key) || (is_string($key) && is_numeric($key))) {
                            $ids[] = $key;
                        } else {
                            // Recursively process nested arrays
                            $ids = array_merge($ids, $this->flattenParticipantIds($item));
                        }
                    }
                } else {
                    // Direct value - could be the key or the value
                    if (is_numeric($key) || (is_string($key) && is_numeric($key))) {
                        $ids[] = $key;
                    } else {
                        $ids[] = $item;
                    }
                }
            }
        } else {
            // Single value
            $ids[] = $data;
        }
        
        // Clean and validate IDs
        $ids = array_filter($ids, function($id) {
            return !empty($id) && $id !== null && (is_numeric($id) || is_string($id));
        });
        
        return array_values(array_unique($ids));
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
