<?php

namespace Modules\Core\Livewire\Concerns;

use Modules\Core\Support\PortalNavigation;

trait ChecksPortalPermission
{
    protected function authorizePortal(int|string $permissionId): void
    {
        $permissions = session('user.permissions', []);
        if (! PortalNavigation::can($permissions, $permissionId)) {
            abort(403, 'You do not have permission to access this page.');
        }
    }
}
