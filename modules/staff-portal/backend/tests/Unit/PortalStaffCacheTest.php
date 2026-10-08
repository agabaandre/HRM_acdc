<?php

namespace Tests\Unit;

use App\Support\PortalReadCache;
use App\Support\PortalReferenceCache;
use App\Support\PortalStaffCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PortalStaffCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_bust_bumps_staff_and_dashboard_versions_and_clears_reference_keys(): void
    {
        $staffBefore = PortalReadCache::version('staff');
        $dashboardBefore = PortalReadCache::version('dashboard');

        Cache::put(PortalStaffCache::FILTER_OPTIONS_KEY, ['regions' => []], 300);
        Cache::put(PortalReferenceCache::FORM_LOOKUPS_KEY, ['jobs' => []], 300);

        // Simulate a cached directory payload keyed by current staff version.
        $dirKey = PortalReadCache::key('staff', 'directory', 1, ['page' => 1]);
        PortalReadCache::remember($dirKey, fn () => ['stale' => true]);

        $dashKey = PortalReadCache::key('dashboard', 'snapshot-maps', 1, []);
        PortalReadCache::remember($dashKey, fn () => ['staff' => 1]);

        PortalStaffCache::bust();

        $this->assertNotSame($staffBefore, PortalReadCache::version('staff'));
        $this->assertNotSame($dashboardBefore, PortalReadCache::version('dashboard'));
        $this->assertNull(Cache::get(PortalStaffCache::FILTER_OPTIONS_KEY));
        $this->assertNull(Cache::get(PortalReferenceCache::FORM_LOOKUPS_KEY));

        // New keys after bust must miss and rebuild.
        $freshDir = PortalReadCache::remember(
            PortalReadCache::key('staff', 'directory', 1, ['page' => 1]),
            fn () => ['fresh' => true]
        );
        $this->assertSame(['fresh' => true], $freshDir);

        $freshDash = PortalReadCache::remember(
            PortalReadCache::key('dashboard', 'snapshot-maps', 1, []),
            fn () => ['staff' => 99]
        );
        $this->assertSame(['staff' => 99], $freshDash);
    }
}
