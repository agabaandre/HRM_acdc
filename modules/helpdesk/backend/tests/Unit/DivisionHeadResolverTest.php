<?php

namespace Tests\Unit;

use App\Services\DivisionHeadResolver;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DivisionHeadResolverTest extends TestCase
{
    public function test_uses_division_head_when_no_oic(): void
    {
        $resolver = new DivisionHeadResolver;
        $id = $resolver->effectiveHeadStaffId([
            'division_head' => 42,
            'head_oic_id' => null,
        ]);

        $this->assertSame(42, $id);
    }

    public function test_uses_active_oic_when_in_window(): void
    {
        $resolver = new DivisionHeadResolver;
        $id = $resolver->effectiveHeadStaffId([
            'division_head' => 42,
            'head_oic_id' => 99,
            'head_oic_start_date' => '2020-01-01',
            'head_oic_end_date' => '2099-12-31',
        ], Carbon::parse('2026-07-27'));

        $this->assertSame(99, $id);
    }

    public function test_falls_back_to_head_when_oic_expired(): void
    {
        $resolver = new DivisionHeadResolver;
        $id = $resolver->effectiveHeadStaffId([
            'division_head' => 42,
            'head_oic_id' => 99,
            'head_oic_start_date' => '2020-01-01',
            'head_oic_end_date' => '2021-01-01',
        ], Carbon::parse('2026-07-27'));

        $this->assertSame(42, $id);
    }
}
