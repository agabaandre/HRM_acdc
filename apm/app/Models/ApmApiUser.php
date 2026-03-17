<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class ApmApiUser extends Authenticatable implements JWTSubject
{
    protected $table = 'apm_api_users';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'password',
        'name',
        'role',
        'auth_staff_id',
        'status',
        'created_at',
        'changed',
        'isChanged',
        'photo',
        'signature',
        'is_approved',
        'is_verfied',
        'langauge',
        'email',
        'last_used_at',
        'firebase_token',
        'remember_token',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'firebase_token', // do not expose in JSON; used server-side only for FCM
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'changed' => 'date',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'auth_staff_id' => $this->auth_staff_id,
            'email' => $this->email,
        ];
    }

    /**
     * Staff linked via auth_staff_id (same as staff.staff_id).
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'auth_staff_id', 'staff_id');
    }

    /**
     * Active = status 1 (for API login check).
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->status;
    }

    /**
     * Get session-like array for use with PendingApprovalsService etc. (API context)
     */
    public function toSessionArray(): array
    {
        $staff = $this->staff;
        $name = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : ($this->name ?? $this->email);
        // Photo from APM staff table (fresh), same source as web auth session user->photo
        $staffId = (int) ($this->auth_staff_id ?? 0);
        $photoFromStaff = $staffId > 0
            ? Staff::query()->where('staff_id', $staffId)->value('photo')
            : null;
        $photo = !empty(trim((string) $photoFromStaff)) ? trim((string) $photoFromStaff) : ($this->photo ?? null);
        $data = [
            'staff_id' => $this->auth_staff_id,
            'division_id' => $staff->division_id ?? null,
            'permissions' => [],
            'name' => $name,
            'email' => $this->email ?? $staff->work_email ?? null,
            'base_url' => config('app.url'),
        ];
        if ($photo !== null && $photo !== '') {
            $data['photo'] = $photo;
        }
        return $data;
    }
}
