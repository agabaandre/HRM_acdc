<?php

namespace App\Services;

use App\Models\WeeklyBriefingSetting;

/**
 * Who may see Division Weekly Brief nav / routes: system admin (role 10) or staff listed in settings contributors.
 */
final class DivisionWeeklyBriefGate
{
    public static function currentRole(): int
    {
        return (int) (user_session('role') ?? user_session('user_role') ?? 0);
    }

    public static function isSystemAdmin(?int $role = null): bool
    {
        $r = $role ?? self::currentRole();

        return $r === 10;
    }

    public static function isListedContributor(int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        return WeeklyBriefingSetting::current()
            ->contributors()
            ->where('staff_id', $staffId)
            ->exists();
    }

    public static function canAccessModule(): bool
    {
        if (self::isSystemAdmin()) {
            return true;
        }

        return self::isListedContributor((int) user_session('staff_id'));
    }

    /**
     * @return list<string>
     */
    public static function contributionKeysForCurrentUser(): array
    {
        if (! self::canAccessModule()) {
            return [];
        }

        $settings = WeeklyBriefingSetting::current();
        $staffId = (int) user_session('staff_id');
        $role = self::currentRole();

        if (self::isSystemAdmin($role)) {
            if (! $settings->contributors()->exists()) {
                return [];
            }

            return $settings->contributors()
                ->distinct()
                ->pluck('contribution_key')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $settings->contributors()
            ->where('staff_id', $staffId)
            ->pluck('contribution_key')
            ->unique()
            ->values()
            ->all();
    }

    public static function mayUseContributionKey(string $contributionKey): bool
    {
        if ($contributionKey === '' || ! self::canAccessModule()) {
            return false;
        }

        $settings = WeeklyBriefingSetting::current();
        if (! $settings->contributors()->exists()) {
            return false;
        }

        if (self::isSystemAdmin()) {
            return $settings->contributors()
                ->where('contribution_key', $contributionKey)
                ->exists();
        }

        return $settings->contributors()
            ->where('staff_id', (int) user_session('staff_id'))
            ->where('contribution_key', $contributionKey)
            ->exists();
    }
}
