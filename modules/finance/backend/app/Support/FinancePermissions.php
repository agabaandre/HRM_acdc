<?php

namespace App\Support;

/**
 * Staff Portal permission codes for Finance (seeded in portal).
 */
final class FinancePermissions
{
    /** CBP tile / module access */
    public const MODULE = '92';

    /** Finance module settings */
    public const SETTINGS = '93';

    /** Manage Funding Portfolio data entry (extramural and related sections) */
    public const MANAGE_FUNDING_PORTFOLIO = '124';

    /**
     * @param  list<int|string>  $permissions
     */
    public static function canManageFundingPortfolio(array $permissions): bool
    {
        return self::hasAny($permissions, [
            self::MANAGE_FUNDING_PORTFOLIO,
            self::SETTINGS,
            RiskPermissions::MANAGE,
        ]);
    }

    /**
     * @param  list<int|string>  $permissions
     */
    public static function canAccessModule(array $permissions): bool
    {
        return self::hasAny($permissions, [self::MODULE, self::SETTINGS, self::MANAGE_FUNDING_PORTFOLIO]);
    }

    /**
     * Org-wide budgeted execution (all divisions). Staff without this see their division(s) only.
     *
     * @param  list<int|string>  $permissions
     */
    public static function canViewAllBudgetExecution(array $permissions, int $roleId = 0): bool
    {
        if ($roleId === 10) {
            return true;
        }

        return self::canAccessModule($permissions);
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
