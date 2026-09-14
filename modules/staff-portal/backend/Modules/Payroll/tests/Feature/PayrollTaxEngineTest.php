<?php

namespace Modules\Payroll\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Models\PayrollTaxBand;
use Modules\Payroll\Services\TaxRuleService;
use Tests\TestCase;

class PayrollTaxEngineTest extends TestCase
{
    public function test_progressive_band_tax_math(): void
    {
        $svc = app(TaxRuleService::class);
        $bands = collect([
            new PayrollTaxBand(['from_amount' => 0, 'to_amount' => 1000, 'rate_percent' => 0, 'fixed_amount' => 0]),
            new PayrollTaxBand(['from_amount' => 1000, 'to_amount' => 5000, 'rate_percent' => 10, 'fixed_amount' => 0]),
            new PayrollTaxBand(['from_amount' => 5000, 'to_amount' => null, 'rate_percent' => 20, 'fixed_amount' => 0]),
        ]);

        $this->assertSame(600.0, $svc->computeTax(6000, $bands));
        $this->assertSame(0.0, $svc->computeTax(500, $bands));
        $this->assertSame(400.0, $svc->computeTax(5000, $bands));
    }
}
