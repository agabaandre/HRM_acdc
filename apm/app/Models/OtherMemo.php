<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtherMemo extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'memo_type_slug',
        'memo_type_name_snapshot',
        'ref_prefix_snapshot',
        'signature_style_snapshot',
        'fields_schema_snapshot',
        'payload',
        'approvers_config',
        'document_number',
        'staff_id',
        'division_id',
        'is_division_specific_snapshot',
        'division_code_snapshot',
        'overall_status',
        'active_sequence',
        'returned_at_sequence',
        'current_approver_staff_id',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'fields_schema_snapshot' => 'array',
            'payload' => 'array',
            'approvers_config' => 'array',
            'active_sequence' => 'integer',
            'returned_at_sequence' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_division_specific_snapshot' => 'boolean',
        ];
    }

    public function approvalTrails(): HasMany
    {
        return $this->hasMany(OtherMemoApprovalTrail::class, 'other_memo_id')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'current_approver_staff_id', 'staff_id');
    }

    public function memoTypeDefinition(): BelongsTo
    {
        return $this->belongsTo(MemoTypeDefinition::class, 'memo_type_slug', 'slug');
    }

    public function approversCount(): int
    {
        $c = $this->approvers_config;

        return is_array($c) ? count($c) : 0;
    }

    public function approverAtSequence(int $sequence): ?array
    {
        $c = $this->approvers_config;
        if (! is_array($c) || $sequence < 1) {
            return null;
        }

        foreach ($c as $row) {
            if ((int) ($row['sequence'] ?? 0) === $sequence) {
                return $row;
            }
        }

        return null;
    }
}
