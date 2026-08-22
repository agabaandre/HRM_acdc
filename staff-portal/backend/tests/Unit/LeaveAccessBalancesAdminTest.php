<?php

namespace Tests\Unit;

use Modules\Leave\Support\LeaveAccess;
use Tests\TestCase;

class LeaveAccessBalancesAdminTest extends TestCase
{
    public function test_only_system_admin_and_hr_roles_are_balances_admins(): void
    {
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(17));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(21));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(23));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(24));
        $this->assertFalse(LeaveAccess::isBalancesAdminRole(null));

        $this->assertTrue(LeaveAccess::isBalancesAdminRole(10));
        $this->assertTrue(LeaveAccess::isBalancesAdminRole(20));
        $this->assertTrue(LeaveAccess::isBalancesAdminRole(22));
    }
}
