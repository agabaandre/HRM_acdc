<?php

namespace Modules\Payroll\Support;

final class PayrollPermissions
{
    public const VIEW_HUB = 110;

    public const MANAGE_SETUP = 111;

    public const MANAGE_STAFF_PAY = 112;

    public const RUN_PAYROLL = 113;

    public const MANAGE_LOANS = 114;

    public const APPROVE_LOANS = 115;

    public const REQUEST_LOAN = 116;

    public const VIEW_OWN_PAYSLIPS = 117;

    /**
     * @return list<array{id: int, name: string, definition: string, module: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => self::VIEW_HUB,
                'name' => 'view_payroll_hub',
                'definition' => 'View Payroll Hub',
                'module' => 'payroll',
            ],
            [
                'id' => self::MANAGE_SETUP,
                'name' => 'manage_payroll_setup',
                'definition' => 'Manage Payroll Setup',
                'module' => 'payroll',
            ],
            [
                'id' => self::MANAGE_STAFF_PAY,
                'name' => 'manage_staff_pay',
                'definition' => 'Manage Staff Pay Master',
                'module' => 'payroll',
            ],
            [
                'id' => self::RUN_PAYROLL,
                'name' => 'run_payroll',
                'definition' => 'Run Payroll (Simulate/Post)',
                'module' => 'payroll',
            ],
            [
                'id' => self::MANAGE_LOANS,
                'name' => 'manage_payroll_loans',
                'definition' => 'Manage Payroll Loans',
                'module' => 'payroll',
            ],
            [
                'id' => self::APPROVE_LOANS,
                'name' => 'approve_payroll_loans',
                'definition' => 'Approve Payroll Loans',
                'module' => 'payroll',
            ],
            [
                'id' => self::REQUEST_LOAN,
                'name' => 'request_payroll_loan',
                'definition' => 'Request Payroll Loan/Advance',
                'module' => 'payroll',
            ],
            [
                'id' => self::VIEW_OWN_PAYSLIPS,
                'name' => 'view_own_payslips',
                'definition' => 'View Own Payslips',
                'module' => 'payroll',
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public static function moduleAccessIds(): array
    {
        return [
            self::VIEW_HUB,
            self::MANAGE_SETUP,
            self::MANAGE_STAFF_PAY,
            self::RUN_PAYROLL,
            self::MANAGE_LOANS,
            self::APPROVE_LOANS,
            self::REQUEST_LOAN,
            self::VIEW_OWN_PAYSLIPS,
        ];
    }

    /**
     * @return list<int>
     */
    public static function adminAllIds(): array
    {
        return self::moduleAccessIds();
    }

    /**
     * @return list<int>
     */
    public static function hrIds(): array
    {
        return [
            self::VIEW_HUB,
            self::MANAGE_SETUP,
            self::MANAGE_STAFF_PAY,
            self::RUN_PAYROLL,
            self::MANAGE_LOANS,
            self::APPROVE_LOANS,
        ];
    }
}
