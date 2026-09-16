<?php

namespace Tests\Unit;

use App\Support\SapExportParser;
use Tests\Support\BuildsMinimalXlsx;
use Tests\TestCase;

class SapExportParserTest extends TestCase
{
    use BuildsMinimalXlsx;

    public function test_total_rows_map_released_budget_and_balance(): void
    {
        $path = $this->makeMinimalXlsx([
            ['Fund center', 'Fund center Text', 'GL Account', 'Total Released Budget', 'Released Budget balance'],
            ['CDC0300001SP', 'Example', '', '6000000', '0'],
            ['CDC0300001SP', '', '0000512012', '100', '50'],
            ['CDC0300001SP', '', 'Total', '6000000', '35582.69'],
            ['CDC0300002SP', 'Other', '', '4500000', '0'],
            ['CDC0300002SP', '', 'Total', '4500000', '1990668.19'],
        ], 'Sheet1');

        $result = (new SapExportParser)->parse($path);

        $this->assertCount(5, $result['rows']);
        $this->assertCount(2, $result['totals']);
        $this->assertSame('CDC0300001SP', $result['totals'][0]['fund_center']);
        $this->assertSame(6000000.0, $result['totals'][0]['total_released_budget']);
        $this->assertSame(35582.69, $result['totals'][0]['released_budget_balance']);
        $this->assertSame('CDC0300002SP', $result['totals'][1]['fund_center']);
        $this->assertSame(4500000.0, $result['totals'][1]['total_released_budget']);
        $this->assertSame(1990668.19, $result['totals'][1]['released_budget_balance']);
    }

    public function test_missing_required_header_throws(): void
    {
        $path = $this->makeMinimalXlsx([
            ['Fund center', 'GL Account', 'Total Released Budget'],
            ['CDC1', 'Total', '100'],
        ], 'Sheet1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required header');
        (new SapExportParser)->parse($path);
    }
}
