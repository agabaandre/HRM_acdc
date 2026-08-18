<?php

namespace Tests\Unit;

use Modules\Performance\Support\PerformanceRichText;
use Tests\TestCase;

class PerformanceRichTextTest extends TestCase
{
    public function test_empty_quill_html_is_empty(): void
    {
        $this->assertTrue(PerformanceRichText::isEmpty('<p><br></p>'));
        $this->assertTrue(PerformanceRichText::isEmpty('   '));
        $this->assertFalse(PerformanceRichText::isEmpty('<p>Ship dashboard</p>'));
    }

    public function test_plain_text_is_preserved(): void
    {
        $this->assertSame('Ship dashboard', PerformanceRichText::sanitize('Ship dashboard'));
        $this->assertStringContainsString('Ship dashboard', PerformanceRichText::toSafeHtml("Ship\ndashboard"));
        $this->assertStringContainsString('<br>', PerformanceRichText::toSafeHtml("Ship\ndashboard"));
    }

    public function test_sanitize_keeps_lists_and_strips_scripts(): void
    {
        $html = '<p>Done</p><ul><li>One</li></ul><script>alert(1)</script>';
        $clean = PerformanceRichText::sanitize($html);

        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>One</li>', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
    }

    public function test_sanitize_strips_javascript_href(): void
    {
        $clean = PerformanceRichText::sanitize('<p><a href="javascript:alert(1)">x</a></p>');

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_sanitize_form_payload_cleans_objective_html(): void
    {
        $payload = PerformanceRichText::sanitizeFormPayload([
            'objectives' => [
                1 => [
                    'objective' => '<p>Lead <script>alert(1)</script>review</p>',
                    'indicator' => '<p>KPI</p>',
                    'weight' => 50,
                ],
            ],
            'training_contributions' => '<p>Coaching</p>',
        ]);

        $this->assertStringNotContainsString('<script>', $payload['objectives'][1]['objective']);
        $this->assertStringContainsString('Lead', $payload['objectives'][1]['objective']);
        $this->assertSame('<p>Coaching</p>', $payload['training_contributions']);
        $this->assertSame(50, $payload['objectives'][1]['weight']);
    }
}
