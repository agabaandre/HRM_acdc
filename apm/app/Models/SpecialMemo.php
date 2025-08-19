<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;

class SpecialMemo extends Model
{
    use HasFactory, HasApprovalWorkflow;

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
        'is_special_memo',
        'is_draft',
        'budget',
        'attachment',
        'status',
        'overall_status',
        'forward_workflow_id',
        'approval_level',
        'next_approval_level',
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
        'internal_participants' => 'array',
        'budget' => 'array',
        'attachment' => 'array',
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
    
    /**
     * Request type relationship.
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
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


    /**
     * Get the budget as an array.
     */
    public function getBudgetAttribute($value)
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

}
