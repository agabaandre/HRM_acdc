<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerPlainTextTest extends TestCase
{
    public function test_to_plain_text_strips_tags_and_decodes_entities(): void
    {
        $html = '<p>Hello <strong>world</strong></p><p>Line&nbsp;two</p>';

        $this->assertSame("Hello world\nLine two", HtmlSanitizer::toPlainText($html));
    }

    public function test_to_plain_text_returns_empty_for_blank_html(): void
    {
        $this->assertSame('', HtmlSanitizer::toPlainText('<p>  </p>'));
        $this->assertNull(HtmlSanitizer::toPlainText(null));
    }
}
