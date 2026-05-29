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

    public const WORK_MODE_REMOTE = 'remote';

    public const WORK_MODE_ONSITE = 'onsite';

    /** @var list<string> */
    public const VALID_WORK_MODES = [self::WORK_MODE_REMOTE, self::WORK_MODE_ONSITE];

    protected $table = 'helpdesk_profiles';

    protected $fillable = [
        'user_id',
        'staff_id',
        'staff_portal_role',
        'staff_portal_permissions',
        'sap_no',
        'role',
        'is_designated_agent',
        'can_manage_kb',
        'can_reassign_tickets',
        'directorate_id',
        'division_id',
        'duty_station',
        'work_mode',
        'work_mode_updated_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'work_mode_updated_at' => 'datetime',
            'can_manage_kb' => 'boolean',
            'can_reassign_tickets' => 'boolean',
            'is_designated_agent' => 'boolean',
            'staff_portal_permissions' => 'array',
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

    /**
     * True when this profile may CRUD knowledge-base articles — admins always,
     * other roles only when explicitly granted via Settings → Agents.
     */
    public function canManageKnowledgeBase(): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        return (bool) $this->can_manage_kb;
    }

    /**
     * True when this profile may reassign tickets to another agent — admins
     * always, other roles only when explicitly granted via Settings → Agents.
     */
    public function canReassignTickets(): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        return (bool) $this->can_reassign_tickets;
    }
}
