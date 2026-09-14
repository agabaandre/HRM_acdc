<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskSoftwareRequestTeamMember extends Model
{
    protected $table = 'helpdesk_software_request_team_members';

    protected $fillable = [
        'software_request_id',
        'user_id',
        'staff_id',
        'member_name',
        'member_email',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'software_request_id' => 'integer',
            'user_id' => 'integer',
            'staff_id' => 'integer',
        ];
    }

    public function softwareRequest(): BelongsTo
    {
        return $this->belongsTo(HelpdeskSoftwareRequest::class, 'software_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
