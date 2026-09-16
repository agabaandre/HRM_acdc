<?php

namespace Tests\Unit;

use App\Services\IntramuralSapBudgetExecutionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IntramuralSapBudgetExecutionServiceTest extends TestCase
{
    public function test_execution_rate_formula_via_list_mapping_logic(): void
    {
        $service = new IntramuralSapBudgetExecutionService;
        $method = new ReflectionMethod(IntramuralSapBudgetExecutionService::class, 'money');
        $method->setAccessible(true);

        $approved = $method->invoke($service, '1,000.00');
        $balance = $method->invoke($service, '250');
        $this->assertSame(1000.0, $approved);
        $this->assertSame(250.0, $balance);
        $this->assertEqualsWithDelta(0.75, ($approved - $balance) / $approved, 0.0001);
    }
}
