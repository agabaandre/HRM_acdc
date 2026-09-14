<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_tags_and_event_handlers(): void
    {
        $dirty = '<p>Hello</p><script>document.cookie</script><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)>';
        $clean = HtmlSanitizer::sanitize($dirty);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('Hello', $clean);
    }

    public function test_allows_safe_formatting(): void
    {
        $html = '<p><strong>Bold</strong> and <a href="https://example.org">link</a></p>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<strong>Bold</strong>', $clean);
        $this->assertStringContainsString('href="https://example.org"', $clean);
    }

    public function test_sql_injection_string_is_escaped_as_text(): void
    {
        $dirty = "<p>' OR 1=1 --</p>";
        $clean = HtmlSanitizer::sanitize($dirty);

        $this->assertStringContainsString("' OR 1=1", $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }
}
