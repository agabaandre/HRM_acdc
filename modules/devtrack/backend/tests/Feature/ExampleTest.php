<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertJsonStructure(['status', 'checks', 'timestamp']);
    }
}
