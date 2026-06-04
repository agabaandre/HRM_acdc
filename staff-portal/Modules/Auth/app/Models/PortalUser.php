<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Staff\Models\Staff;

class PortalUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'user';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'password',
        'role',
        'auth_staff_id',
        'status',
        'allow_email_login',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'allow_email_login' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'auth_staff_id', 'staff_id');
    }

    /**
     * Session / SSO payload shape expected by APM, Finance, and Helpdesk.
     *
     * @return array<string, mixed>
     */
    public function toSessionArray(): array
    {
        $this->loadMissing('staff');
        $staff = $this->staff;
        $permissions = $this->resolvePermissionIds();

        return [
            'user_id' => $this->user_id,
            'staff_id' => (int) $this->auth_staff_id,
            'name' => trim(($staff?->fname ?? '').' '.($staff?->lname ?? '')) ?: $this->name,
            'email' => $staff?->work_email ?? '',
            'role' => $this->role,
            'role_id' => (int) $this->role,
            'base_url' => rtrim((string) config('staff-portal.base_url'), '/').'/',
            'permissions' => $permissions,
            'division_id' => $staff?->activeContract?->division_id ?? null,
            'photo' => $staff?->photo,
            'langauge' => $this->langauge ?? 'en',
        ];
    }

    /**
     * Group + per-user permissions (matches CI3 Auth_mdl::user_permissions).
     *
     * @return list<int|string>
     */
    protected function resolvePermissionIds(): array
    {
        $perms = [];

        if (\App\Support\LegacySchema::has('group_permissions')) {
            $perms = \Illuminate\Support\Facades\DB::table('group_permissions')
                ->where('group_id', $this->role)
                ->pluck('permission_id')
                ->map(fn ($id) => is_numeric($id) ? (int) $id : (string) $id)
                ->all();
        }

        if (\App\Support\LegacySchema::has('user_permissions')) {
            $userPerms = \Illuminate\Support\Facades\DB::table('user_permissions')
                ->where('user_id', $this->user_id)
                ->pluck('permission_id')
                ->map(fn ($id) => is_numeric($id) ? (int) $id : (string) $id)
                ->all();
            $perms = array_merge($perms, $userPerms);
        }

        return array_values(array_unique($perms, SORT_REGULAR));
    }
}
