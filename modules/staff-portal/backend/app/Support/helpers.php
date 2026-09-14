<?php

use Modules\Core\Support\PortalNavigation;

if (! function_exists('portal_can')) {
    /**
     * @param  int|string  $permissionId
     */
    function portal_can(int|string $permissionId): bool
    {
        $permissions = session('user.permissions', []);

        return PortalNavigation::can($permissions, $permissionId);
    }
}

if (! function_exists('nav_active')) {
    function nav_active(string ...$segments): string
    {
        return PortalNavigation::active(...$segments);
    }
}
