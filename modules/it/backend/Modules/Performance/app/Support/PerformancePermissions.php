<?php

namespace Modules\Performance\Support;

/**
 * Canonical performance permission IDs (portal extensions).
 *
 * 74 = staff performance module access (legacy).
 * 83 = allow_return_ppa (legacy HR return override).
 */
final class PerformancePermissions
{
    /** Access the Staff Performance module / forms. */
    public const MODULE_ACCESS = 74;

    /** Return a submitted/approved PPA, midterm, or endterm to the employee. */
    public const ALLOW_RETURN = 83;

    /**
     * Change first/second supervisors on draft PPA, midterm, and endterm forms.
     * Seeded to System Administrator and HR roles; not granted to employees by default.
     */
    public const CHANGE_SUPERVISORS = 127;

    /**
     * @return list<array{id: int, name: string, definition: string, module: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => self::CHANGE_SUPERVISORS,
                'name' => 'change_performance_supervisors',
                'definition' => 'Change supervisors on draft PPA, midterm, and endterm forms',
                'module' => 'performance',
            ],
        ];
    }

    /**
     * Role / user_group ids that receive CHANGE_SUPERVISORS on setup.
     * 10 System Administrator, 20 HR Manager, 22 HR Admin.
     *
     * @return list<int>
     */
    public static function defaultChangeSupervisorRoleIds(): array
    {
        return [
            PerformanceSettingsAccess::ROLE_SYSTEM_ADMIN,
            PerformanceSettingsAccess::ROLE_HR_MANAGER,
            PerformanceSettingsAccess::ROLE_HR_ADMIN,
        ];
    }
}
