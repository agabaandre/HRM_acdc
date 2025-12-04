<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class SpecialMemo extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'special_memos'; // Uses the 'activities' table

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'activity_id',
        'staff_id',
        'division_id',
        'responsible_person_id',
        'fund_type_id',
        'budget_id',
        'workplan_activity_code',
        'date_from',
        'date_to',
        'location_id',
        'total_participants',
        'internal_participants',
        'total_external_participants',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'justification',
        'supporting_reasons',
        'is_special_memo',
        'is_draft',
        'budget_breakdown',
        'attachment',
        'status',
        'overall_status',
        'document_number',
        'forward_workflow_id',
        'approval_level',
        'next_approval_level',
        'approval_order_map',
        'available_budget',
    ];

    /**
     * Casts for attribute types.
     */
    protected $casts = [
        'staff_id' => 'integer',
        'division_id' => 'integer',
        'request_type_id' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'location_id' => 'array',
        'responsible_person_id' => 'integer',
        'fund_type_id' => 'integer',
        'budget_id' => 'array',
        
        'internal_participants' => 'array',
        'budget_breakdown' => 'array',
        // Don't cast attachment to array - let the accessor handle double-encoded JSON
        'is_special_memo' => 'boolean',
        'is_draft' => 'boolean',
    ];

    protected $appends = [
        'formatted_dates',
    ];

    /**
     * Staff member who submitted the special memo.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
    
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
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
    
    /**
     * Responsible person relationship.
     */
    public function responsiblePerson()
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }
    
    /**
     * Request type relationship.
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class, 'fund_type_id');
    }

    /**
     * Forward workflow relationship.
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'forward_workflow_id');
    }

    /**
     * Reverse workflow relationship.
     */
    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'reverse_workflow_id');
    }

    // --- Accessors & Utility ---

    public function getFormattedDatesAttribute(): string
    {
        if ($this->date_from && $this->date_to) {
            return \Carbon\Carbon::parse($this->date_from)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($this->date_to)->format('M j, Y');
        }
        return '';
    }

    /**
     * Get the workflow definition for the current approval level.
     *
     * @return \App\Models\WorkflowDefinition|null
     */
    public function getWorkflowDefinitionAttribute()
    {
        $definitions = WorkflowDefinition::where('approval_order', $this->approval_level)
            ->where('workflow_id', $this->forward_workflow_id)
            ->where('is_enabled', 1)
            ->get();

        if ($definitions->count() > 1 && $definitions[0]->category) {
            $category = null;
            if ($this->division) {
                $category = $this->division->category;
            }
            return $definitions->where('category', $category)->first();
        }

        return ($definitions->count() > 0) ? $definitions[0] : WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)->where('approval_order', 1)->first();
    }

    /**
     * Get the current actor (approver) for the current approval level.
     *
     * @return \App\Models\Staff|null
     */
    public function getCurrentActorAttribute()
    {
        if ($this->overall_status == 'approved') {
            return null;
        }

        $role = $this->workflow_definition;
        $staff_id = $this->staff_id;

        if ($role) {
            if ($role->is_division_specific) {
                if ($this->division && isset($this->division->{$role->division_reference_column})) {
                    $staff_id = $this->division->{$role->division_reference_column};
                }
            } else {
                $approver = Approver::select('staff_id')
                    ->where('workflow_dfn_id', $role->id)
                    ->first();
                if ($approver) {
                    $staff_id = $approver->staff_id;
                }
            }
        }

        return Staff::select('lname', 'fname', 'staff_id')
            ->where('staff_id', $staff_id)
            ->first();
    }

    public function getRecipientsAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getAttachmentAttribute($value)
    {
        // Handle double-encoded JSON (sometimes happens when saving)
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            // First decode
            $decoded = json_decode($value, true);
            
            // If still a string, it might be double-encoded
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function setAttachmentAttribute($value)
    {
        // Ensure attachment is stored as JSON string (single-encoded)
        if (is_array($value)) {
            $this->attributes['attachment'] = json_encode($value);
        } elseif (is_string($value)) {
            // If it's already a JSON string, store it as-is
            $this->attributes['attachment'] = $value;
        } else {
            $this->attributes['attachment'] = json_encode([]);
        }
    }

    public function getInternalParticipantsAttribute($value)
    {
       $data = $this->cleanJson($value);
        
        $result = [];
        foreach ($data as $staffId => $participantDetails) {
            $staff = \App\Models\Staff::find($staffId) ?: \App\Models\Staff::find((int)$staffId);
            if ($staff) {
                $result[] = [
                    'staff' => $staff,
                    'participant_start' => $participantDetails['participant_start'],
                    'participant_end' => $participantDetails['participant_end'],
                    'participant_days' => $participantDetails['participant_days']
                ];
            } else {
                $result[] = [
                    'staff' => null,
                    'participant_start' => $participantDetails['participant_start'],
                    'participant_end' => $participantDetails['participant_end'],
                    'participant_days' => $participantDetails['participant_days']
                ];
            }
        }
        return $result;
    }

    private function cleanJson($value)
    {
         // Remove extra quotes if present
         if (is_string($value) && strlen($value) > 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
            $value = substr($value, 1, -1);
        }
        // Unescape slashes
        $value = stripslashes($value);
        // First decode
        $data = json_decode($value, true);
        // If still a string, decode again (double-encoded)
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return $data;
    }

    public function getLocationsAttribute()
    {
        $data = $this->cleanJson($this->location_id);
        return Location::whereIn('id', $data)->pluck('name')->implode(', ');
    }

    /**
     * Get the approval trails for this special memo.
     */
    public function approvalTrails()
    {
        return $this->morphMany(ApprovalTrail::class, 'model', 'model_type', 'model_id');
    }


    /**
     * Get the budget breakdown as an array.
     */
    public function getBudgetBreakdownAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            return is_array($decoded) ? $decoded : [];
        }

        return [];
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

    public function getResourceUrlAttribute()
    {
        return route('special-memos.show', $this->id);
    }

}
