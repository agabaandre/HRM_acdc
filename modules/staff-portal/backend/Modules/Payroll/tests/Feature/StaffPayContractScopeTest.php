<?php

namespace Modules\Payroll\Tests\Feature;

use Modules\Payroll\Services\StaffPayService;
use Tests\TestCase;

class StaffPayContractScopeTest extends TestCase
{
    public function test_contract_scope_methods_exist(): void
    {
        $this->assertTrue(method_exists(StaffPayService::class, 'currentContractId'));
        $this->assertTrue(method_exists(StaffPayService::class, 'getForContract'));
        $this->assertTrue(method_exists(StaffPayService::class, 'inheritFromPreviousContract'));
        $this->assertTrue(method_exists(StaffPayService::class, 'bundle'));
    }
}
