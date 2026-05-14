<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskProfile extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_AGENT = 'agent';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_AUDITOR = 'auditor';

    protected $table = 'helpdesk_profiles';

    protected $fillable = [
        'user_id',
        'staff_id',
        'sap_no',
        'role',
        'directorate_id',
        'division_id',
        'duty_station',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStaffRole(): bool
    {
        return in_array($this->role, [
            self::ROLE_AGENT,
            self::ROLE_SUPERVISOR,
            self::ROLE_ADMIN,
            self::ROLE_AUDITOR,
        ], true);
    }
}
