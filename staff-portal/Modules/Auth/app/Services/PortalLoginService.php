<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Audit\Services\AuditLogService;
use Modules\Auth\Models\PortalUser;

class PortalLoginService
{
    public function __construct(
        protected AuditLogService $auditLog,
    ) {}

    public function login(PortalUser $user, bool $remember = false, string $auditMessage = 'User logged in'): void
    {
        Auth::login($user, $remember);
        session([
            'user' => $user->toSessionArray(),
            'last_activity' => now(),
        ]);
        $this->auditLog->log($auditMessage, ['event_type' => 'login']);
    }
}
