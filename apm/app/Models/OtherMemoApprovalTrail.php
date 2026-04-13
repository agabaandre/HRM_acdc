<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks approval actions for {@see OtherMemo} (same conceptual fields as approval_trails leaf:
 * approval_order, staff_id, action, remarks).
 */
class OtherMemoApprovalTrail extends Model
{
    protected $table = 'other_memos_approval_trails';

    protected $fillable = [
        'other_memo_id',
        'approval_order',
        'staff_id',
        'action',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'other_memo_id' => 'integer',
            'approval_order' => 'integer',
            'staff_id' => 'integer',
        ];
    }

    public function otherMemo(): BelongsTo
    {
        return $this->belongsTo(OtherMemo::class, 'other_memo_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Label for the timeline (matches approval_trails.approver_role_name usage in matrices partial).
     */
    public function getApproverRoleNameAttribute(): ?string
    {
        $order = (int) $this->approval_order;
        if ($order === 0) {
            return 'Creator';
        }

        $memo = $this->relationLoaded('otherMemo') ? $this->otherMemo : $this->otherMemo()->first();
        if (! $memo) {
            return 'Approver';
        }

        $row = $memo->approverAtSequence($order);

        return is_array($row) ? (string) ($row['role_label'] ?? 'Approver') : 'Approver';
    }
}
