<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_health_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'service',
                'version',
                'laravel',
                'branding' => ['primary', 'primary_dark', 'accent', 'secondary'],
                'integrations',
                'staff_share_api' => ['configured'],
            ]);
    }
}
