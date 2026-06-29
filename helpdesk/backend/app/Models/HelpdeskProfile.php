<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'can_delete_request_attachments',
        'can_change_ticket_category',
        'can_manage_it_assets',
        'can_manage_licenses',
        'can_submit_software_requests',
        'can_approve_software_requests',
        'can_manage_software_requests',
        'grant_helpdesk_admin',
        'grant_supervisor_access',
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
            'can_delete_request_attachments' => 'boolean',
            'can_change_ticket_category' => 'boolean',
            'can_manage_it_assets' => 'boolean',
            'can_manage_licenses' => 'boolean',
            'can_submit_software_requests' => 'boolean',
            'can_approve_software_requests' => 'boolean',
            'can_manage_software_requests' => 'boolean',
            'grant_helpdesk_admin' => 'boolean',
            'grant_supervisor_access' => 'boolean',
            'is_designated_agent' => 'boolean',
            'staff_portal_permissions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True when this profile should appear in agent routing, support groups, and the agent desk.
     * Portal admins (role 10) keep role=admin but participate as agents when designated.
     */
    public function actsAsAgent(): bool
    {
        return $this->role === self::ROLE_AGENT || $this->is_designated_agent === true;
    }

    /**
     * @param  Builder<HelpdeskProfile>  $query
     * @return Builder<HelpdeskProfile>
     */
    public function scopeActsAsAgent(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('role', self::ROLE_AGENT)
                ->orWhere('is_designated_agent', true);
        });
    }

    /**
     * True when tickets may be assigned to this profile (auto-routing or manual reassign).
     */
    public function canBeAssignedTickets(): bool
    {
        return $this->actsAsAgent() || in_array($this->role, [
            self::ROLE_SUPERVISOR,
            self::ROLE_ADMIN,
        ], true);
    }

    /**
     * @param  Builder<HelpdeskProfile>  $query
     * @return Builder<HelpdeskProfile>
     */
    public function scopeAssignableAsTicketAssignee(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->actsAsAgent()
                ->orWhereIn('role', [self::ROLE_SUPERVISOR, self::ROLE_ADMIN]);
        });
    }

    /**
     * @param  Builder<HelpdeskProfile>  $query
     * @return Builder<HelpdeskProfile>
     */
    public function scopeWithoutAgentDuties(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('role', '!=', self::ROLE_AGENT)
                ->where(function (Builder $q) {
                    $q->where('is_designated_agent', false)
                        ->orWhereNull('is_designated_agent');
                });
        });
    }

    public function isStaffRole(): bool
    {
        return in_array($this->role, [
            self::ROLE_AGENT,
            self::ROLE_SUPERVISOR,
            self::ROLE_ADMIN,
            self::ROLE_AUDITOR,
        ], true) || $this->grant_supervisor_access === true;
    }

    /**
     * True when this profile may access Helpdesk Settings and admin APIs.
     * Grants full admin even without Staff portal role 10 (APM system admin).
     */
    public function isHelpdeskAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->grant_helpdesk_admin === true;
    }

    /**
     * True when this profile has supervisor-level ticket visibility and actions.
     */
    public function hasSupervisorAccess(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return in_array($this->role, [self::ROLE_SUPERVISOR], true)
            || $this->grant_supervisor_access === true;
    }

    /**
     * True when this profile may CRUD knowledge-base articles — admins always,
     * other roles only when explicitly granted via Settings → Agents.
     */
    public function canManageKnowledgeBase(): bool
    {
        if ($this->isHelpdeskAdmin()) {
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
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_reassign_tickets;
    }

    /**
     * True when this profile may remove request attachments on open tickets — admins
     * always, other roles only when explicitly granted via Settings → Agents.
     */
    public function canDeleteRequestAttachments(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_delete_request_attachments;
    }

    /**
     * True when this profile may change ticket category on open tickets — admins
     * always, other roles only when explicitly granted via Settings → Agents.
     */
    public function canChangeTicketCategory(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_change_ticket_category;
    }

    public function canManageItAssets(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_manage_it_assets;
    }

    public function canManageLicenses(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_manage_licenses;
    }

    public function canSubmitSoftwareRequests(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return $this->can_submit_software_requests !== false;
    }

    public function canApproveSoftwareRequests(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_approve_software_requests || (bool) $this->can_manage_software_requests;
    }

    public function canManageSoftwareRequests(): bool
    {
        if ($this->isHelpdeskAdmin()) {
            return true;
        }

        return (bool) $this->can_manage_software_requests;
    }

    public function hasAnyToolsAccess(): bool
    {
        return $this->canManageItAssets()
            || $this->canManageLicenses()
            || $this->canSubmitSoftwareRequests()
            || $this->canApproveSoftwareRequests()
            || $this->canManageSoftwareRequests();
    }
}
