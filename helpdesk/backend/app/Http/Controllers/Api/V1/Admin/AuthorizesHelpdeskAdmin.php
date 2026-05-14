<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\HelpdeskProfile;
use Illuminate\Http\Request;

trait AuthorizesHelpdeskAdmin
{
    protected function ensureHelpdeskAdmin(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless($p && $p->role === HelpdeskProfile::ROLE_ADMIN, 403, 'Admin role required.');
    }
}
