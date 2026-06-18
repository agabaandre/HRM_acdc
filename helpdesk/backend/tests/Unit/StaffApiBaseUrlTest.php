<?php

namespace Tests\Unit;

use App\Support\StaffApiBaseUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffApiBaseUrlTest extends TestCase
{
    #[Test]
    public function it_rewrites_mdns_local_hostnames_to_localhost_for_server_side_calls(): void
    {
        $resolved = StaffApiBaseUrl::resolve('https://Users-MacBook-Pro.local/staff/');

        $this->assertSame('http://localhost/staff', $resolved);
    }

    #[Test]
    public function it_leaves_localhost_urls_unchanged(): void
    {
        $resolved = StaffApiBaseUrl::resolve('http://localhost/staff/');

        $this->assertSame('http://localhost/staff', $resolved);
    }

    #[Test]
    public function it_defaults_empty_config_to_localhost_staff(): void
    {
        $this->assertSame('http://localhost/staff', StaffApiBaseUrl::resolve(''));
    }
}
