<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserApprovalStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'organisation',
    'division',
    'directorate',
    'staff_id',
    'staff_synced_at',
    'registration_source',
    'is_admin',
    'is_active',
    'approval_status',
    'approved_at',
    'approved_by',
    'created_by',
    'rejected_at',
    'rejection_reason',
    'two_factor_email_enabled',
    'two_factor_totp_enabled',
    'totp_required',
    'two_factor_totp_secret',
    'two_factor_totp_recovery_codes',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_totp_secret',
    'two_factor_totp_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'approval_status' => UserApprovalStatus::class,
            'registration_source' => \App\Enums\UserRegistrationSource::class,
            'staff_id' => 'integer',
            'staff_synced_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'two_factor_email_enabled' => 'boolean',
            'two_factor_totp_enabled' => 'boolean',
            'totp_required' => 'boolean',
            'two_factor_totp_secret' => 'encrypted',
            'two_factor_totp_recovery_codes' => 'array',
        ];
    }

    public function isApproved(): bool
    {
        return $this->approval_status === UserApprovalStatus::Approved;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === UserApprovalStatus::Pending;
    }

    /**
     * Approved partner accounts may create team users after authenticator enrollment.
     */
    public function canManageTeamAccounts(): bool
    {
        return ! $this->is_admin
            && $this->is_active
            && $this->isApproved()
            && $this->two_factor_totp_enabled;
    }

    /**
     * Admins always can. Partners may register clients only after authenticator (TOTP) is enabled.
     */
    public function canRegisterClients(): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->canManageTeamAccounts();
    }

    public function externalIntegrations(): BelongsToMany
    {
        return $this->belongsToMany(ExternalIntegration::class)->withTimestamps();
    }

    public function assignedWorkItems(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'assignee_id');
    }

    public function projectRoleAssignments(): HasMany
    {
        return $this->hasMany(ProjectRoleAssignment::class);
    }

    public function staffProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_email_enabled || $this->two_factor_totp_enabled;
    }

    /**
     * Admin-created accounts must enroll an authenticator and keep it enabled.
     */
    public function requiresTotp(): bool
    {
        return (bool) $this->totp_required;
    }

    /**
     * True until the required authenticator has been confirmed.
     */
    public function mustSetupTotp(): bool
    {
        return $this->requiresTotp() && ! $this->two_factor_totp_enabled;
    }

    /**
     * @return list<string>
     */
    public function enabledTwoFactorMethods(): array
    {
        $methods = [];

        if ($this->two_factor_email_enabled) {
            $methods[] = 'email';
        }

        if ($this->two_factor_totp_enabled) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    /**
     * Integration IDs this user may view email logs for.
     * Admins get null (unrestricted). Non-admins get their assigned apps only.
     *
     * @return list<int>|null
     */
    public function allowedExternalIntegrationIds(): ?array
    {
        if ($this->is_admin) {
            return null;
        }

        return $this->externalIntegrations()
            ->pluck('external_integrations.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessExternalIntegration(?int $integrationId): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if ($integrationId === null) {
            return false;
        }

        $allowed = $this->allowedExternalIntegrationIds() ?? [];

        return in_array($integrationId, $allowed, true);
    }
}
