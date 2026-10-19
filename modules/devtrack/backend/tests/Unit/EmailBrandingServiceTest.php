<?php

namespace Tests\Unit;

use App\Models\BrandingSetting;
use App\Models\ExternalIntegration;
use App\Services\EmailBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailBrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrap_html_keeps_body_without_branding_header(): void
    {
        BrandingSetting::query()->create([
            'app_name' => 'Africa CDC Email Server',
            'tagline' => 'Staff portal email gateway',
            'primary_color' => '#0d7a3a',
            'secondary_color' => '#c9a227',
        ]);

        $integration = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret'),
            'api_key_prefix' => 'secr••••',
            'is_active' => true,
        ]);

        $html = app(EmailBrandingService::class)->wrapHtml(
            '<p>Hello from Email Server</p>',
            $integration,
        );

        $this->assertStringContainsString('Hello from Email Server', $html);
        $this->assertStringNotContainsString('Africa CDC Email Server', $html);
        $this->assertStringNotContainsString('Staff portal email gateway', $html);
        $this->assertStringNotContainsString('via APM', $html);
    }

    public function test_resolve_from_name_uses_integration_name(): void
    {
        BrandingSetting::query()->create([
            'app_name' => 'Africa CDC Email Server',
            'primary_color' => '#0d7a3a',
            'secondary_color' => '#c9a227',
        ]);

        $integration = ExternalIntegration::query()->create([
            'name' => 'APM',
            'slug' => 'apm',
            'api_key_hash' => ExternalIntegration::hashClientSecret('secret'),
            'api_key_prefix' => 'secr••••',
            'is_active' => true,
        ]);

        $fromName = app(EmailBrandingService::class)->resolveFromName($integration);

        $this->assertSame('APM', $fromName);
    }

    public function test_resolve_from_name_falls_back_to_app_name_without_integration(): void
    {
        BrandingSetting::query()->create([
            'app_name' => 'Africa CDC Email Server',
            'primary_color' => '#0d7a3a',
            'secondary_color' => '#c9a227',
        ]);

        $fromName = app(EmailBrandingService::class)->resolveFromName(null, 'Provider Mailer');

        $this->assertSame('Provider Mailer', $fromName);
    }
}
