<?php

namespace Tests\Unit;

use App\Services\ResidualRiskCalculator;
use PHPUnit\Framework\TestCase;

class ResidualRiskCalculatorTest extends TestCase
{
    public function test_inherent_critical_and_residual_with_substantial(): void
    {
        $c = new ResidualRiskCalculator(static fn (int $s): string => match (true) {
            $s <= 4 => 'Low',
            $s <= 9 => 'Medium',
            $s <= 15 => 'High',
            default => 'Critical',
        });
        $r = $c->compute(5, 5, 3, true);
        $this->assertSame(25, $r['inherent_score']);
        $this->assertSame('Critical', $r['inherent_rating']);
        $this->assertSame(2, $r['residual_likelihood']);
        $this->assertSame(4, $r['residual_impact']);
        $this->assertSame(8, $r['residual_score']);
        $this->assertSame('Medium', $r['residual_rating']);
    }

    public function test_not_assessed_copies_inherent(): void
    {
        $c = new ResidualRiskCalculator(static fn (int $s): string => 'Critical');
        $r = $c->compute(5, 5, 0, false);
        $this->assertSame(25, $r['residual_score']);
        $this->assertSame(5, $r['residual_likelihood']);
        $this->assertSame(5, $r['residual_impact']);
        $this->assertSame('No Change', $r['movement']);
    }

    public function test_floors_at_one(): void
    {
        $c = new ResidualRiskCalculator(static fn (int $s): string => 'Low');
        $r = $c->compute(1, 1, 4, true);
        $this->assertSame(1, $r['residual_likelihood']);
        $this->assertSame(1, $r['residual_impact']);
    }
}
