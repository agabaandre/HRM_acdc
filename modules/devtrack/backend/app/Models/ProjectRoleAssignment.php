<?php

namespace App\Models;

use App\Enums\NamedProjectRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRoleAssignment extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'staff_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => NamedProjectRole::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return array{id: int, name: string, email: string|null}|null
     */
    public function assigneePayload(): ?array
    {
        if ($this->staff) {
            return [
                'id' => $this->staff->id,
                'name' => $this->staff->fullName(),
                'email' => $this->staff->work_email,
            ];
        }

        if ($this->user) {
            return [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $staff = $this->staff ? [
            'id' => $this->staff->id,
            'staff_id' => $this->staff->staff_id,
            'name' => $this->staff->fullName(),
            'email' => $this->staff->work_email,
            'work_email' => $this->staff->work_email,
            'division_name' => $this->staff->division_name,
            'duty_station_name' => $this->staff->duty_station_name,
        ] : null;

        $user = $this->user ? [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ] : null;

        // Prefer staff for display; fall back to linked user for legacy rows.
        $display = $staff ? [
            'id' => $staff['id'],
            'name' => $staff['name'],
            'email' => $staff['email'],
        ] : $user;

        return [
            'id' => $this->id,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'staff_id' => $this->staff_id,
            'staff' => $staff,
            'user_id' => $this->user_id,
            'user' => $display,
        ];
    }
}
