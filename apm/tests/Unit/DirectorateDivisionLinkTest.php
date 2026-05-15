<?php

namespace Tests\Unit;

use App\Services\DirectorateDivisionLink;
use PHPUnit\Framework\TestCase;

class DirectorateDivisionLinkTest extends TestCase
{
    public function test_belongs_when_directorate_id_matches(): void
    {
        $this->assertTrue(DirectorateDivisionLink::belongs(5, 100, 5, 200));
    }

    public function test_belongs_when_director_ids_match_even_without_fk(): void
    {
        $this->assertTrue(DirectorateDivisionLink::belongs(0, 42, 9, 42));
        $this->assertFalse(DirectorateDivisionLink::belongs(0, 42, 9, 99));
    }

    public function test_belongs_false_when_neither_link_matches(): void
    {
        $this->assertFalse(DirectorateDivisionLink::belongs(0, 10, 9, 20));
        $this->assertFalse(DirectorateDivisionLink::belongs(3, 10, 9, 20));
        $this->assertFalse(DirectorateDivisionLink::belongs(0, 0, 9, 20));
    }

    public function test_belongs_false_for_invalid_directorate_id(): void
    {
        $this->assertFalse(DirectorateDivisionLink::belongs(5, 100, 0, 100));
    }

    public function test_belongs_when_fk_differs_but_director_matches(): void
    {
        // Stale FK should not block when director_id aligns with selected directorate's director.
        $this->assertTrue(DirectorateDivisionLink::belongs(99, 55, 7, 55));
    }

}
