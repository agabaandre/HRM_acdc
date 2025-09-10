<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use App\Models\FundType;
use App\Traits\HasDocumentNumber;

class ServiceRequest extends Model
{
    use HasFactory, HasDocumentNumber, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'request_number',
        'request_date',
        'staff_id',
        'activity_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'division_id',
        'service_title',
        'description',
        'justification',
        'required_by_date',
        'location',
        'estimated_cost',
        'priority',
        'service_type',
        'specifications',
        'attachments',
        'status',
        'remarks',
        // New budget and approval columns
        'budget_breakdown',
        'internal_participants_cost',
        'external_participants_cost',
        'other_costs',
        'original_total_budget',
        'new_total_budget',
        'fund_type_id',
        'title',
        'responsible_person_id',
        'document_number',
        'budget_id',
        'model_type',
        'source_id',
        'source_type',
        'approval_level',
        'next_approval_level',
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
            'staff_id' => 'integer',
            'activity_id' => 'integer',
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'division_id' => 'integer',
            'request_date' => 'date',
            'required_by_date' => 'date',
            'estimated_cost' => 'decimal:2',
            'specifications' => 'array',
            'attachments' => 'array',
            // New budget and approval columns
            'budget_breakdown' => 'array',
            'internal_participants_cost' => 'array',
            'external_participants_cost' => 'array',
            'other_costs' => 'array',
            'original_total_budget' => 'decimal:2',
            'new_total_budget' => 'decimal:2',
            'fund_type_id' => 'integer',
            'responsible_person_id' => 'integer',
            'budget_id' => 'array',
            'approval_level' => 'integer',
            'next_approval_level' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }
    
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
    
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
    
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class, 'fund_type_id');
    }
    
    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }
    
    /**
     * Get the workflow definition for the current approval level
     */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'approval_level', 'approval_order')
            ->where('workflow_id', $this->forward_workflow_id);
    }
    
    /**
     * Get the current actor (person responsible for current approval level)
     */
    public function currentActor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'current_actor_id', 'staff_id');
    }
    
    /**
     * Generate a unique request number following the system pattern.
     * Format: SRV/DHIS/Q2/YYYY/ACTIVITY_ID/0001
     * 
     * @param string $divisionCode Division code (e.g., DHIS)
     * @param string $quarter Quarter (e.g., Q1, Q2, Q3, Q4)
     * @param int $year Year from activity start date
     * @param int $activityId Activity ID from source
     * @return string
     */
    public static function generateRequestNumber($divisionCode = 'DHIS', $quarter = 'Q1', $year = null, $activityId = null): string
    {
        $year = $year ?: date('Y');
        $activityId = $activityId ?: 1;
        
        // Get the latest request number for this activity
        $latestRequest = self::where('request_number', 'like', "SRV/{$divisionCode}/{$quarter}/{$year}/{$activityId}/%")
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = 1;
        
        if ($latestRequest) {
            // Extract the number part from the request number
            $parts = explode('/', $latestRequest->request_number);
            $lastNumber = intval(end($parts));
            $nextNumber = $lastNumber + 1;
        }
        
        // Format the request number: SRV/DHIS/Q2/YYYY/ACTIVITY_ID/0001
        return sprintf("SRV/%s/%s/%s/%s/%04d", $divisionCode, $quarter, $year, $activityId, $nextNumber);
    }
    
    /**
     * Generate short code from division name by removing joining words and using initials
     */
    public static function generateShortCodeFromDivision(string $name): string
    {
        $ignore = ['of', 'and', 'for', 'the', 'in'];
        $words = preg_split('/\s+/', strtolower($name));
        $initials = array_map(function ($word) use ($ignore) {
            return in_array($word, $ignore) ? '' : strtoupper($word[0]);
        }, $words);
        
        return implode('', array_filter($initials));
    }

    public function getResourceUrlAttribute()
    {
        return route('service-requests.show', $this->id);
    }
}
