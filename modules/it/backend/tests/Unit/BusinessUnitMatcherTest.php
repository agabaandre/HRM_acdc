<?php

namespace Tests\Unit;

use App\Services\BusinessUnitMatcher;
use PHPUnit\Framework\TestCase;

class BusinessUnitMatcherTest extends TestCase
{
    public function test_parses_phc_chshp(): void
    {
        $org = [
            'divisions' => [
                [
                    'division_id' => 35,
                    'division_short_name' => 'CHSHP',
                    'division_name' => 'Community Health Systems & Health Promotion',
                    'directorate_id' => 1,
                    'division_head' => 169,
                ],
            ],
            'directorates' => [
                [
                    'id' => 1,
                    'name' => 'Centre for Primary Health Care',
                    'aliases' => ['PHC'],
                ],
            ],
        ];
        $m = new BusinessUnitMatcher($org);
        $r = $m->match('PHC/CHSHP');
        $this->assertSame(35, $r['division_id']);
        $this->assertSame(1, $r['directorate_id']);
        $this->assertNull($r['unmapped']);
        $this->assertSame(169, $r['division_head']);
    }

    public function test_matches_short_name_alone(): void
    {
        $org = [
            'divisions' => [
                [
                    'division_id' => 10,
                    'division_short_name' => 'EPR',
                    'division_name' => 'Emergency Preparedness and Response',
                    'directorate_id' => null,
                    'division_head' => 5,
                ],
            ],
            'directorates' => [],
        ];
        $m = new BusinessUnitMatcher($org);
        $r = $m->match('EPR');
        $this->assertSame(10, $r['division_id']);
        $this->assertNull($r['unmapped']);
    }

    public function test_unmapped_when_unknown(): void
    {
        $m = new BusinessUnitMatcher(['divisions' => [], 'directorates' => []]);
        $r = $m->match('Unknown Unit XYZ');
        $this->assertNull($r['division_id']);
        $this->assertSame('Unknown Unit XYZ', $r['unmapped']);
    }

    public function test_alias_western_rcc(): void
    {
        $org = [
            'divisions' => [
                [
                    'division_id' => 15,
                    'division_short_name' => 'WRCC',
                    'division_name' => 'Western RCC',
                    'directorate_id' => null,
                    'division_head' => 88,
                ],
            ],
            'directorates' => [],
        ];
        $m = new BusinessUnitMatcher($org);
        $r = $m->match('Western - RCC');
        $this->assertSame(15, $r['division_id']);
        $this->assertNull($r['unmapped']);
    }
}
