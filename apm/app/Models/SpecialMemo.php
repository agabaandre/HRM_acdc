<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasApprovalWorkflow;

class SpecialMemo extends Model
{
    use HasFactory, HasApprovalWorkflow;

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

    // --- Accessors & Utility ---

    public function getFormattedDatesAttribute(): string
    {
        if ($this->date_from && $this->date_to) {
            return \Carbon\Carbon::parse($this->date_from)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($this->date_to)->format('M j, Y');
        }
        return '';
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
}
