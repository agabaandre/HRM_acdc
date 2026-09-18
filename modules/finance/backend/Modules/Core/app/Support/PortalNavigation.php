<?php

namespace Modules\Core\Support;

class PortalNavigation
{
    /**
     * @param  list<int|string>  $permissions
     */
    public static function can(array $permissions, int|string $code): bool
    {
        return in_array($code, $permissions, true)
            || in_array((int) $code, array_map('intval', $permissions), true);
    }

    public static function active(string ...$segments): string
    {
        $current = request()->segment(1) ?? '';

        return in_array($current, $segments, true) ? 'active' : '';
    }

    /**
     * Payload for core::partials.cbp-modules-dropdown (top-bar menu, not inline header links).
     *
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function cbpModulesPayload(): array
    {
        $session = session('user', []);
        $current = trim(request()->path(), '/');

        return CbpModulesNav::payload(
            is_array($session) ? $session : [],
            $current,
            '',
            ($current === '' || $current === 'home') ? '' : 'staff_portal',
        );
    }
}
