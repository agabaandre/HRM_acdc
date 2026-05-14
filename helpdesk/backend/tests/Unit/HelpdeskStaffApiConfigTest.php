<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Staff Share credentials are resolved from HELPDESK_STAFF_API_* with APM-style fallbacks
 * (BASE_URL, STAFF_API_BASE_URL, STAFF_API_USERNAME, …) — keep config/helpdesk.php aligned with apm/config/services.php.
 */
class HelpdeskStaffApiConfigTest extends TestCase
{
    #[Test]
    public function helpdesk_config_chains_apm_style_env_for_staff_api_base_and_credentials(): void
    {
        $path = config_path('helpdesk.php');
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringContainsString("env('STAFF_API_BASE_URL'", $contents);
        $this->assertStringContainsString("env('BASE_URL'", $contents);
        $this->assertStringContainsString("env('STAFF_API_USERNAME'", $contents);
        $this->assertStringContainsString("env('STAFF_API_PASSWORD'", $contents);
        $this->assertStringContainsString("env('STAFF_API_TOKEN'", $contents);
    }
}
