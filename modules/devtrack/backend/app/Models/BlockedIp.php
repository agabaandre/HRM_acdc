<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
        'audit_log_id',
        'is_active',
        'unblocked_at',
        'unblocked_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'unblocked_at' => 'datetime',
        ];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
