<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\Request;

/**
 * Allow knowledge-base CRUD for admins OR any user whose helpdesk_profile has
 * `can_manage_kb=1` (granted from Settings → Agents).
 */
trait AuthorizesKbManager
{
    protected function ensureKbManager(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless(
            $p && $p->canManageKnowledgeBase(),
            403,
            'You need the admin role or the “manage knowledge base” permission to do this.'
        );
    }
}
