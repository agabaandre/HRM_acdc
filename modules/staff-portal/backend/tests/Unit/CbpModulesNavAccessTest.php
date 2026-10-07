<?php

namespace Tests\Unit;

use Modules\Core\Support\CbpModulesNav;
use Modules\Core\Support\StaffSsoLaunch;
use PHPUnit\Framework\TestCase;

class CbpModulesNavAccessTest extends TestCase
{
    public function test_staff_portal_lands_on_profile_without_dashboard_permission(): void
    {
        $href = CbpModulesNav::staffPortalSpaHref(
            'dashboard',
            [84],
            '/staff/staff-portal/',
        );

        $this->assertSame('/staff/staff-portal/profile', $href);
    }

    public function test_staff_portal_lands_on_dashboard_with_permission_76(): void
    {
        $href = CbpModulesNav::staffPortalSpaHref(
            'dashboard',
            [76, 84],
            '/staff/staff-portal/',
        );

        $this->assertSame('/staff/staff-portal/dashboard', $href);
    }

    public function test_staff_portal_honours_role_alternate_profile_path(): void
    {
        $href = CbpModulesNav::staffPortalSpaHref(
            'auth/profile',
            [84],
            '/staff/staff-portal/',
        );

        $this->assertSame('/staff/staff-portal/profile', $href);
    }

    public function test_enabled_module_is_launchable_without_permission_code(): void
    {
        $row = (object) [
            'permission_code' => '84',
            'is_production' => 1,
        ];

        $this->assertTrue(StaffSsoLaunch::userCanAccessModule([
            'permissions' => [],
            'role_id' => 17,
        ], $row));
    }

    public function test_non_production_module_hidden_from_non_admin(): void
    {
        $row = (object) [
            'permission_code' => '84',
            'is_production' => 0,
        ];

        $this->assertFalse(StaffSsoLaunch::userCanAccessModule([
            'permissions' => [84],
            'role_id' => 17,
        ], $row));

        $this->assertTrue(StaffSsoLaunch::userCanAccessModule([
            'permissions' => [],
            'role_id' => 10,
        ], $row));
    }
}
