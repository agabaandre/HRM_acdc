<?php

namespace Modules\Leave\Support;

/**
 * Canonical leave permission IDs (legacy CI3 + portal extensions).
 *
 * Backward compatible:
 * - 37 / 73 keep their existing meanings
 * - 77 (domain_controller) still grants “view all leave” where it was overloaded
 * - HR role 20 still grants admin/settings access
 */
final class LeavePermissions
{
    /** Make leave request / access self-service leave. */
    public const MAKE_REQUEST = 37;

    /** Approve leave requests (supervisor/HR tooling). */
    public const APPROVE_REQUEST = 73;

    /**
     * Legacy overload: AD “domain_controller” was also used for view-all leave in CI3.
     * Prefer VIEW_ALL for new assignments.
     */
    public const LEGACY_VIEW_ALL = 77;

    /** View leave requests for all staff. */
    public const VIEW_ALL = 95;

    /** Administer staff leave opening balances (per-staff + bulk fill). */
    public const MANAGE_BALANCES = 96;

    /** Manage leave policy and leave types. */
    public const MANAGE_SETTINGS = 97;

    /** Manage public holiday calendars and holiday compensatory rules. */
    public const MANAGE_HOLIDAYS = 98;

    /**
     * @return list<array{id: int, name: string, definition: string, module: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => self::MAKE_REQUEST,
                'name' => 'make_leave_request',
                'definition' => 'Make Leave Request',
                'module' => 'leave',
            ],
            [
                'id' => self::APPROVE_REQUEST,
                'name' => 'approve_leave_request',
                'definition' => 'Approve Leave Request',
                'module' => 'leave',
            ],
            [
                'id' => self::VIEW_ALL,
                'name' => 'view_all_leave_requests',
                'definition' => 'View All Leave Requests',
                'module' => 'leave',
            ],
            [
                'id' => self::MANAGE_BALANCES,
                'name' => 'manage_leave_balances',
                'definition' => 'Manage Leave Balances',
                'module' => 'leave',
            ],
            [
                'id' => self::MANAGE_SETTINGS,
                'name' => 'manage_leave_settings',
                'definition' => 'Manage Leave Settings',
                'module' => 'leave',
            ],
            [
                'id' => self::MANAGE_HOLIDAYS,
                'name' => 'manage_leave_holidays',
                'definition' => 'Manage Leave Holidays',
                'module' => 'leave',
            ],
        ];
    }

    /**
     * Any of these grants the Leave nav / module shell.
     *
     * @return list<int>
     */
    public static function moduleAccessIds(): array
    {
        return [
            self::MAKE_REQUEST,
            self::APPROVE_REQUEST,
            self::VIEW_ALL,
            self::MANAGE_BALANCES,
            self::MANAGE_SETTINGS,
            self::MANAGE_HOLIDAYS,
            self::LEGACY_VIEW_ALL,
        ];
    }
}
