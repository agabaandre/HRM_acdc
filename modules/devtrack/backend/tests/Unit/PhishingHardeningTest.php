<?php

namespace Tests\Unit;

use App\Models\BrandingSetting;
use App\Support\MailHeaderSanitizer;
use App\Support\SafeInternalRedirect;
use App\Support\TrustedFrontendUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhishingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_frontend_url_rejects_foreign_hosts(): void
    {
        config([
            'app.url' => 'https://notifications.africacdc.org',
            'app.frontend_url' => 'https://evil.example/phish',
        ]);

        $this->assertSame('https://notifications.africacdc.org', TrustedFrontendUrl::base());
        $this->assertFalse(TrustedFrontendUrl::isTrustedFrontend('https://evil.example'));
        $this->assertTrue(TrustedFrontendUrl::isTrustedFrontend('https://notifications.africacdc.org'));
    }

    public function test_trusted_frontend_url_allows_same_host_different_port(): void
    {
        config([
            'app.url' => 'http://localhost:8089',
            'app.frontend_url' => 'http://localhost:3006',
        ]);

        $this->assertSame('http://localhost:3006', TrustedFrontendUrl::base());
    }

    public function test_mail_header_sanitizer_strips_crlf(): void
    {
        $sanitized = MailHeaderSanitizer::line("Hello\r\nBcc: evil@example.com world");
        $this->assertSame('Hello Bcc: evil@example.com world', $sanitized);
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertStringNotContainsString("\n", $sanitized);
        $this->assertTrue(MailHeaderSanitizer::containsDangerousCharacters("x\ny"));
    }

    public function test_safe_internal_redirect_blocks_open_redirects(): void
    {
        $this->assertSame('/api/documentation', SafeInternalRedirect::path('/api/documentation'));
        $this->assertNull(SafeInternalRedirect::path('https://evil.example'));
        $this->assertNull(SafeInternalRedirect::path('//evil.example'));
        $this->assertNull(SafeInternalRedirect::path('/\\evil.example'));
    }

    public function test_branding_rejects_external_logo_urls(): void
    {
        $branding = BrandingSetting::current();
        $branding->forceFill([
            'logo_path' => 'https://evil.example/logo.png',
        ])->save();

        $this->assertNull($branding->fresh()->logoUrl());
    }
}
