<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskSoftwareRequestApproval extends Model
{
    protected $table = 'helpdesk_software_request_approvals';

    protected $fillable = [
        'software_request_id',
        'approver_user_id',
        'approver_name',
        'approval_role',
        'decision',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'software_request_id' => 'integer',
            'approver_user_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function softwareRequest(): BelongsTo
    {
        return $this->belongsTo(HelpdeskSoftwareRequest::class, 'software_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
