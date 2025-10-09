<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class NonTravelMemo extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
        'overall_status',
        'approval_level',
        'next_approval_level',
        'approval_order_map',
        'workplan_activity_code',
        'staff_id',
        'division_id',
        'fund_type_id',
        'memo_date',
        'location_id',
        'non_travel_memo_category_id',
        'budget_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'justification',
        'budget_breakdown',
        'attachment',
        'is_draft',
        'document_number',
        'available_budget',
    ];

    protected $appends = ['workflow_definition', 'current_actor'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'approval_level' => 'integer',
            'next_approval_level' => 'integer',
            'staff_id' => 'integer',
            'division_id' => 'integer',
            'fund_type_id' => 'integer',
            'memo_date' => 'date',
            'location_id' => 'array',
            'non_travel_memo_category_id' => 'integer',
            'budget_id' => 'array',
            'budget_breakdown' => 'array',
            'attachment' => 'array',
            'is_draft' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function nonTravelMemoCategory(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemoCategory::class, 'non_travel_memo_category_id');
    }

    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class, 'fund_type_id');
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

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'forward_workflow_id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }

    public function approvalTrails()
    {
        return $this->morphMany(\App\Models\ApprovalTrail::class, 'model', 'model_type', 'model_id');
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
                // Get the first active approver for this workflow definition
                $approver = Approver::select('staff_id')
                    ->where('workflow_dfn_id', $role->id)
                    ->where(function($query) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', now());
                    })
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
        return route('non-travel.show', $this->id);
    }


}
