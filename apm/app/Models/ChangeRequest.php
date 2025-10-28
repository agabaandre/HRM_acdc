<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class ChangeRequest extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'change_request';

    protected $fillable = [
        'parent_memo_id',
        'parent_memo_model',
        'activity_id',
        'special_memo_id',
        'non_travel_memo_id',
        'request_arf_id',
        'service_request_id',
        'has_budget_id_changed',
        'has_internal_participants_changed',
        'has_number_of_participants_changed',
        'has_participant_days_changed',
        'has_request_type_id_changed',
        'request_type_id',
        'has_total_external_participants_changed',
        'has_location_changed',
        'has_memo_date_changed',
        'has_date_stayed_quarter',
        'has_activity_title_changed',
        'has_activity_request_remarks_changed',
        'has_is_single_memo_changed',
        'has_budget_breakdown_changed',
        'has_status_changed',
        'has_fund_type_id_changed',
        'document_number',
        'forward_workflow_id',
        'workplan_activity_code',
        'matrix_id',
        'division_id',
        'staff_id',
        'responsible_person_id',
        'supporting_reasons',
        'date_from',
        'date_to',
        'memo_date',
        'location_id',
        'total_participants',
        'internal_participants',
        'total_external_participants',
        'division_staff_request',
        'budget_id',
        'key_result_area',
        'justification',
        'non_travel_memo_category_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_single_memo',
        'budget_breakdown',
        'available_budget',
        'attachment',
        'status',
        'fund_type_id',
        'activity_ref',
        'approval_level',
        'next_approval_level',
        'overall_status',
        'approval_order_map',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['formatted_dates', 'status', 'my_last_action', 'has_passed_at_current_level', 'my_current_level_action'];

    protected $casts = [
        'id' => 'integer',
        'parent_memo_id' => 'integer',
        'activity_id' => 'integer',
        'special_memo_id' => 'integer',
        'non_travel_memo_id' => 'integer',
        'request_arf_id' => 'integer',
        'service_request_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'matrix_id' => 'integer',
        'division_id' => 'integer',
        'staff_id' => 'integer',
        'responsible_person_id' => 'integer',
        'non_travel_memo_category_id' => 'integer',
        'forward_workflow_id' => 'integer',
        'approval_level' => 'integer',
        'next_approval_level' => 'integer',
        'total_participants' => 'integer',
        'total_external_participants' => 'integer',
        'available_budget' => 'decimal:2',
        'date_from' => 'date',
        'date_to' => 'date',
        'memo_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        // JSON fields
        'location_id' => 'array',
        'internal_participants' => 'array',
        'division_staff_request' => 'array',
        'budget_id' => 'array',
        'budget_breakdown' => 'array',
        'attachment' => 'array',
        
        // Boolean fields
        'has_budget_id_changed' => 'boolean',
        'has_internal_participants_changed' => 'boolean',
        'has_number_of_participants_changed' => 'boolean',
        'has_participant_days_changed' => 'boolean',
        'has_request_type_id_changed' => 'boolean',
        'has_total_external_participants_changed' => 'boolean',
        'has_location_changed' => 'boolean',
        'has_memo_date_changed' => 'boolean',
        'has_date_stayed_quarter' => 'boolean',
        'has_activity_title_changed' => 'boolean',
        'has_activity_request_remarks_changed' => 'boolean',
        'has_is_single_memo_changed' => 'boolean',
        'has_budget_breakdown_changed' => 'boolean',
        'has_status_changed' => 'boolean',
        'has_fund_type_id_changed' => 'boolean',
        'is_single_memo' => 'boolean',
    ];

    /**
     * Get the parent memo (polymorphic relationship)
     */
    public function parentMemo(): MorphTo
    {
        return $this->morphTo('parent_memo', 'parent_memo_model', 'parent_memo_id');
    }

    /**
     * Get the activity that this change request is for
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the special memo that this change request is for
     */
    public function specialMemo(): BelongsTo
    {
        return $this->belongsTo(SpecialMemo::class);
    }

    /**
     * Get the non-travel memo that this change request is for
     */
    public function nonTravelMemo(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemo::class);
    }

    /**
     * Get the request ARF that this change request is for
     */
    public function requestArf(): BelongsTo
    {
        return $this->belongsTo(RequestArf::class);
    }

    /**
     * Get the service request that this change request is for
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Get the matrix that this change request belongs to
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    /**
     * Get the division that this change request belongs to
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the staff member who created this change request
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the responsible person for this change request
     */
    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    /**
     * Get the request type for this change request
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * Get the fund type for this change request
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * Get the non-travel memo category for this change request
     */
    public function nonTravelMemoCategory(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemoCategory::class);
    }

    /**
     * Get the forward workflow for this change request
     */

    /**
     * Get the approval trails for this change request
     */
    // public function approvalTrails(): HasMany
    // {
    //     return $this->hasMany(ApprovalTrail::class, 'model_id')
    //         ->where('model_type', 'change_request');
    // }

    /**
     * Get the budget items for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function budgetItems(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestBudget::class);
    // }

    /**
     * Get the participants for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function participants(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestParticipant::class);
    // }

    /**
     * Scope to get change requests by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('overall_status', $status);
    }

    /**
     * Scope to get change requests by approval level
     */
    public function scopeByApprovalLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    /**
     * Scope to get change requests for a specific staff member
     */
    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope to get change requests for a specific division
     */
    public function scopeForDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    /**
     * Get the current approver role for this change request
     */
    public function getCurrentApproverRoleAttribute()
    {
        // This would be implemented based on your workflow logic
        return 'Approver';
    }

    /**
     * Check if this change request has any changes
     */
    public function hasAnyChanges(): bool
    {
        return $this->has_budget_id_changed ||
               $this->has_internal_participants_changed ||
               $this->has_request_type_id_changed ||
               $this->has_total_external_participants_changed ||
               $this->has_location_changed ||
               $this->has_memo_date_changed ||
               $this->has_activity_title_changed ||
               $this->has_activity_request_remarks_changed ||
               $this->has_is_single_memo_changed ||
               $this->has_budget_breakdown_changed ||
               $this->has_status_changed ||
               $this->has_fund_type_id_changed;
    }

    /**
     * Get the summary of changes made
     */
    public function getChangesSummaryAttribute(): array
    {
        $changes = [];
        
        if ($this->has_budget_id_changed) $changes[] = 'Budget Code';
        if ($this->has_internal_participants_changed) $changes[] = 'Internal Participants';
        if ($this->has_request_type_id_changed) $changes[] = 'Request Type';
        if ($this->has_total_external_participants_changed) $changes[] = 'External Participants';
        if ($this->has_location_changed) $changes[] = 'Location';
        if ($this->has_memo_date_changed) $changes[] = 'Memo Date';
        if ($this->has_activity_title_changed) $changes[] = 'Activity Title';
        if ($this->has_activity_request_remarks_changed) $changes[] = 'Activity Remarks';
        if ($this->has_is_single_memo_changed) $changes[] = 'Single Memo Status';
        if ($this->has_budget_breakdown_changed) $changes[] = 'Budget Breakdown';
        if ($this->has_status_changed) $changes[] = 'Status';
        if ($this->has_fund_type_id_changed) $changes[] = 'Fund Type';
        
        return $changes;
    }

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
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
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
    public function getStatusAttribute(){
        $user = session('user', []);
      

        if(isset($user['staff_id']) && $this->staff_id == $user['staff_id'] ){
         return ($this->forward_workflow_id==null)?'Draft':(($this->overall_status =='approved')?'Approved':'Pending');
        }

        $last_log = ActivityApprovalTrail::where('model_id',$this->id)
        ->where('mode_type','App\\Models\\ChangeRequest')->orderBy('id','asc')
        ->first();

        if($last_log)
         return strtoupper($last_log->action);

         if(can_take_action($this))
         return ' Pending';
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
        return route('change-request.show', $this->id);
    }
     public function getMyLastActionAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId) {
            return null;
        }
        
        // Get the current approval level
        $currentApprovalLevel = $this->matrix ? $this->matrix->approval_level : null;
        if (!$currentApprovalLevel) {
            return null;
        }
        
        // First, check if user has any action at the current approval level
        $currentLevelAction = ActivityApprovalTrail::where('activity_id',$this->id)
        ->where('staff_id',$userStaffId)
        ->where('approval_order', $currentApprovalLevel)
        ->where('is_archived', 0)
        ->orderByDesc('id')->first();
        
        if ($currentLevelAction) {
            return $currentLevelAction;
        }
        
        // If no action at current level, check if user has already passed at any previous level
        // This allows previous approvers to see their actions
        $previousPassedAction = ActivityApprovalTrail::where('activity_id',$this->id)
        ->where('staff_id',$userStaffId)
        ->where('action', 'passed')
        ->where('approval_order', '<', $currentApprovalLevel)
        ->where('is_archived', 0)
        ->orderByDesc('id')->first();
        
        return $previousPassedAction;
    }
    public function getHasPassedAtCurrentLevelAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId || !$this->matrix) {
            return false;
        }
        
        $currentApprovalLevel = $this->matrix->approval_level;
        
        // Check if user has passed at the current approval level
        return ActivityApprovalTrail::where('activity_id', $this->id)
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $currentApprovalLevel)
            ->where('action', 'passed')
            ->where('is_archived', 0)
            ->exists();
    }
    public function getMyCurrentLevelActionAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId || !$this->matrix) {
            return null;
        }
        
        $currentApprovalLevel = $this->matrix->approval_level;
        
        // Only return actions at the current approval level
        return ActivityApprovalTrail::where('activity_id', $this->id)
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $currentApprovalLevel)
            ->where('is_archived', 0)
            ->orderByDesc('id')
            ->first();
    }

    public function getFinalApprovalStatusAttribute(){
        // Get the latest approval trail entry for this activity
        $latestTrail = ActivityApprovalTrail::where('activity_id', $this->id)
            ->where('is_archived', 0) // Only consider non-archived trails
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
   
}