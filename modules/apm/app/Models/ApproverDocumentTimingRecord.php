<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of time taken from “received at this level” to approve/reject action.
 * Matches Approver Dashboard receipt rules; {@see \App\Services\ApproverDocumentTimingService}.
 */
class ApproverDocumentTimingRecord extends Model
{
    protected $table = 'approver_document_timing_records';

    protected $fillable = [
        'approval_trail_id',
        'other_memo_approval_trail_id',
        'staff_id',
        'staff_name_snapshot',
        'model_type',
        'model_id',
        'forward_workflow_id',
        'approval_order',
        'action',
        'received_at',
        'acted_at',
        'hours_elapsed',
        'document_type_label',
        'document_title',
        'document_number_snapshot',
        'division_id',
        'division_name_snapshot',
        'workflow_name_snapshot',
        'workflow_role_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'approval_trail_id' => 'integer',
            'other_memo_approval_trail_id' => 'integer',
            'staff_id' => 'integer',
            'model_id' => 'integer',
            'forward_workflow_id' => 'integer',
            'approval_order' => 'integer',
            'division_id' => 'integer',
            'received_at' => 'datetime',
            'acted_at' => 'datetime',
            'hours_elapsed' => 'decimal:4',
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
}
