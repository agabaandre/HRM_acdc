<?php

namespace Modules\Leave\Support;

class LeaveAccess
{
    public static function isHr(): bool
    {
        $role = session('user.role_id') ?? session('user.role') ?? null;

        return (int) $role === 20;
    }

    public static function staffId(): ?int
    {
        $id = session('user.staff_id') ?? auth()->user()?->auth_staff_id;

        return $id ? (int) $id : null;
    }
}
