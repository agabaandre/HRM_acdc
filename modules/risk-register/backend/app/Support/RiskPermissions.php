<?php

namespace App\Support;

/**
 * Staff Portal permission codes for Risk Register (seeded in portal).
 */
final class RiskPermissions
{
    public const MODULE = '118';

    public const VIEW_DIVISION = '119';

    public const VIEW_ALL = '120';

    public const MANAGE = '121';

    /**
     * @param  list<int|string>  $permissions
     */
    public static function canViewAll(array $permissions): bool
    {
        return self::hasAny($permissions, [self::VIEW_ALL, self::MANAGE]);
    }

    /**
     * @param  list<int|string>  $permissions
     */
    public static function canManage(array $permissions): bool
    {
        return self::hasAny($permissions, [self::MANAGE]);
    }

    /**
     * @param  list<int|string>  $permissions
     */
    public static function canViewDivision(array $permissions): bool
    {
        return self::hasAny($permissions, [self::VIEW_DIVISION, self::VIEW_ALL, self::MANAGE, self::MODULE]);
    }

    /**
     * @param  list<int|string>  $permissions
     * @param  list<string>  $codes
     */
    private static function hasAny(array $permissions, array $codes): bool
    {
        $set = [];
        foreach ($permissions as $p) {
            $set[(string) $p] = true;
        }
        foreach ($codes as $code) {
            if (isset($set[$code])) {
                return true;
            }
        }

        return false;
    }
}
