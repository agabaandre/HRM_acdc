<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;

// Additional model imports
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\ApprovalTrail;
use App\Models\FundType;
use App\Models\Funder;

class RequestARF extends Model
{
    use HasFactory, Auditable, HasApprovalWorkflow, HasDocumentNumber;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'request_arfs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'staff_id',
        'responsible_person_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'arf_number',
        'request_date',
        'document_number',
        'division_id',
        'location_id',
        'activity_title',
        'purpose',
        'start_date',
        'end_date',
        'requested_amount',
        'total_amount',
        'accounting_code',
        'budget_breakdown',
        'attachment',
        'fund_type_id',
        'funder_id',
        'extramural_code',
        'model_type',
        'source_id',
        'source_type',
        'internal_participants',
        'approval_level',
        'next_approval_level',
        'overall_status',
        'approval_order_map',
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
            'responsible_person_id' => 'integer',
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'division_id' => 'integer',
            'request_date' => 'date',
            'location_id' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'requested_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'attachment' => 'array',
            'internal_participants' => 'array',
        ];
    }

    /**
     * Get the staff member associated with the ARF request.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get budget breakdown attribute - handle special memos as raw JSON
     */
    public function getBudgetBreakdownAttribute($value)
    {
        // Check if this looks like special memo budget data (has fund code keys and grand_total)
        $isSpecialMemoFormat = false;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                // Only treat as special memo if it has the specific structure AND model_type is SpecialMemo
                $isSpecialMemoFormat = isset($decoded['grand_total']) && 
                    count($decoded) > 1 && // Has at least one fund code + grand_total
                    !isset($decoded[0]) && // Not a simple indexed array
                    $this->model_type === 'App\\Models\\SpecialMemo'; // Only for special memos
            }
        }
        
        // For special memos, return raw JSON string without array conversion
        if ($this->model_type === 'App\\Models\\SpecialMemo' || $isSpecialMemoFormat) {
            return $value; // Return as-is (raw JSON string)
        }
        
        // For other types, use normal array casting
        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * Set budget breakdown attribute - handle special memos as raw JSON
     */
    public function setBudgetBreakdownAttribute($value)
    {
        // Check if this looks like special memo budget data (has fund code keys and grand_total)
        $isSpecialMemoFormat = false;
        if (is_array($value)) {
            $isSpecialMemoFormat = isset($value['grand_total']) && 
                count($value) > 1 && // Has at least one fund code + grand_total
                !isset($value[0]); // Not a simple indexed array
        } elseif (is_string($value)) {
            // Check if it's a JSON string that looks like special memo format
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                // Only treat as special memo if it has the specific structure AND model_type is SpecialMemo
                $isSpecialMemoFormat = isset($decoded['grand_total']) && 
                    count($decoded) > 1 && // Has at least one fund code + grand_total
                    !isset($decoded[0]) && // Not a simple indexed array
                    $this->model_type === 'App\\Models\\SpecialMemo'; // Only for special memos
            }
        }
        
        // For special memos, store as raw JSON string
        if ($this->model_type === 'App\\Models\\SpecialMemo' || $isSpecialMemoFormat) {
            $this->attributes['budget_breakdown'] = is_string($value) ? $value : json_encode($value);
        } else {
            // For other types, use normal array casting
            $this->attributes['budget_breakdown'] = is_array($value) ? json_encode($value) : $value;
        }
    }

    /**
     * Get the responsible person associated with the ARF request.
     */
    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    /**
     * Get the division associated with the ARF request.
     * For activities, get division through the source model.
     * For non-travel and special memos, get division directly.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the actual division for display purposes.
     * This handles the different ways division is stored based on source type.
     */
    public function getActualDivisionAttribute()
    {
        // If we have a direct division_id, use it
        if ($this->division_id) {
            return $this->division;
        }

        // For activities, get division through the source model
        if ($this->model_type === 'App\\Models\\Activity') {
            $sourceModel = $this->getSourceModel();
            if ($sourceModel && $sourceModel->matrix) {
                return $sourceModel->matrix->division;
            }
        }

        // For non-travel and special memos, they should have direct division_id
        return $this->division;
    }

    /**
     * Get the source model instance.
     */
    public function getSourceModel()
    {
        if (!$this->model_type || !$this->source_id) {
            return null;
        }

        try {
            return $this->model_type::find($this->source_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the division head for display purposes.
     */
    public function getDivisionHeadAttribute()
    {
        $division = $this->actual_division;
        if (!$division) {
            return null;
        }

        return Staff::where('staff_id', $division->division_head)->first();
    }

    /**
     * Get the forward workflow for this ARF request.
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    /**
     * Get the reverse workflow for this ARF request.
     */
    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }

    /**
     * Get the fund type associated with the ARF request.
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * Get the funder associated with the ARF request.
     */
    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }
    

    /**
     * Get the workflow definition for the current approval level.
     */
    public function getWorkflowDefinitionAttribute()
    {
        if (!$this->forward_workflow_id || !$this->approval_level) {
            return null;
        }
        
        return WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)
            ->where('approval_order', $this->approval_level)
            ->first();
    }

    /**
     * Get the current actor (approver) for this ARF request.
     */
    public function getCurrentActorAttribute()
    {
        $definition = $this->workflow_definition;
        if (!$definition) {
            return null;
        }

        if ($definition->is_division_specific && $this->division) {
            $staffId = $this->division->{$definition->division_reference_column} ?? null;
            if ($staffId) {
                return Staff::where('staff_id', $staffId)->first();
            }
        } else {
            $approver = Approver::where('workflow_dfn_id', $definition->id)->first();
            if ($approver) {
                return Staff::where('staff_id', $approver->staff_id)->first();
            }
        }

        return null;
    }

    /**
     * Get the approval trails for this ARF request.
     */
    public function approvalTrails()
    {
        return $this->morphMany(ApprovalTrail::class, 'model');
    }
    
    /**
     * Generate a unique ARF number.
     * Format: ARF/DHIS/Q2/activitystartyear/activity_id
     * 
     * @param string $divisionCode Division code (e.g., DHIS)
     * @param string $quarter Quarter (e.g., Q1, Q2, Q3, Q4)
     * @param int $year Year from activity start date
     * @param int $activityId Activity ID
     * @return string
     */
    public static function generateARFNumber($divisionCode = 'DHIS', $quarter = 'Q1', $year = null, $activityId = null): string
    {
        $year = $year ?: date('Y');
        $activityId = $activityId ?: 1;
        
        return "ARF/{$divisionCode}/{$quarter}/{$year}/{$activityId}";
    }
    
    /**
     * Generate short code from division name by removing joining words and using initials
     */
    public static function generateShortCodeFromDivision(string $name): string
    {
        $ignore = ['of', 'and', 'for', 'the', 'in'];
        $words = preg_split('/\s+/', strtolower($name));
        $initials = array_map(function ($word) use ($ignore) {
            // Check if word is empty or in ignore list
            if (empty($word) || in_array($word, $ignore)) {
                return '';
            }
            // Check if word has at least one character before accessing index 0
            return strlen($word) > 0 ? strtoupper($word[0]) : '';
        }, $words);
        
        return implode('', array_filter($initials));
    }
    
    /**
     * Get internal participants details from JSON array
     */
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
            foreach ($data as $item) {
                if (is_array($item)) {
                    // Look for staff_id or id keys
                    if (isset($item['staff_id'])) {
                        $ids[] = $item['staff_id'];
                    } elseif (isset($item['id'])) {
                        $ids[] = $item['id'];
                    } else {
                        // Recursively process nested arrays
                        $ids = array_merge($ids, $this->flattenParticipantIds($item));
                    }
                } else {
                    // Direct value
                    $ids[] = $item;
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

    /**
     * Approve the ARF request and move to next level.
     * 
     * @return bool
     */
    public function approve(): bool
    {
        $this->approval_level = $this->next_approval_level ?? $this->approval_level + 1;
        $this->next_approval_level = $this->approval_level + 1;
        
        // Check if this is the final approval level (assuming max level 3)
        if ($this->approval_level >= 3) {
            $this->overall_status = 'approved';
            $this->status = 'approved';
            $this->next_approval_level = null;
        } else {
            $this->overall_status = 'pending';
        }
        
        return $this->save();
    }

    /**
     * Reject the ARF request.
     * 
     * @return bool
     */
    public function reject(): bool
    {
        $this->overall_status = 'rejected';
        $this->status = 'rejected';
        $this->next_approval_level = null;
        
        return $this->save();
    }

    /**
     * Get the current approval status description.
     * 
     * @return string
     */
    public function getApprovalStatusDescription(): string
    {
        switch ($this->overall_status) {
            case 'draft':
                return 'Draft - Not yet submitted for approval';
            case 'pending':
                return "Pending - Awaiting approval at level {$this->next_approval_level}";
            case 'approved':
                return 'Approved - All approval levels completed';
            case 'rejected':
                return 'Rejected - Request has been rejected';
            default:
                return 'Unknown status';
        }
    }
}
