<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PerformanceWebRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        config([
            'staff-portal.spa_url' => 'https://spa.example/staff/staff-portal/',
        ]);
    }

    public function test_performance_form_routes_redirect_to_spa_urls(): void
    {
        $response = $this->runRoute('/performance/create?period=January-2026-to-December-2026');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://spa.example/staff/staff-portal/performance/create?period=January-2026-to-December-2026',
            $response->headers->get('Location')
        );

        $response = $this->runRoute('/performance/view_ppa/entry-1/100');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://spa.example/staff/staff-portal/performance/form/ppa/entry-1/100',
            $response->headers->get('Location')
        );

        $response = $this->runRoute('/performance/midterm/midterm_review/entry-1/100');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://spa.example/staff/staff-portal/performance/form/midterm/entry-1/100',
            $response->headers->get('Location')
        );

        $response = $this->runRoute('/performance/endterm/endterm_review/entry-1/100');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://spa.example/staff/staff-portal/performance/form/endterm/entry-1/100',
            $response->headers->get('Location')
        );
    }

    protected function runRoute(string $path): Response
    {
        $request = Request::create($path, 'GET');
        $route = app('router')->getRoutes()->match($request);

        app()->instance('request', $request);

        return $route->run();
    }
}
