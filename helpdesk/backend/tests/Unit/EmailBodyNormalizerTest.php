<?php

namespace Tests\Unit;

use App\Services\EmailBodyNormalizer;
use Tests\TestCase;

class EmailBodyNormalizerTest extends TestCase
{
    public function test_strips_outlook_style_html_reply_thread(): void
    {
        $html = <<<'HTML'
<p>I confirm thank you.</p>
<div id="divRplyFwdMsg">
<p>From: Cheick Oumar<br>Sent: Tuesday<br>Subject: RE: access</p>
<p>Previous message body…</p>
</div>
HTML;

        $out = (new EmailBodyNormalizer)->toTicketHtml($html, 'html');
        $this->assertStringContainsString('I confirm thank you', $out);
        $this->assertStringNotContainsString('Previous message body', $out);
        $this->assertStringNotContainsString('divRplyFwdMsg', $out);
    }

    public function test_strips_plain_text_from_sent_headers(): void
    {
        $text = "Please reset my password.\n\nFrom: Boss <boss@example.org>\nSent: Monday\nTo: helpdesk\nSubject: RE: access\n\nOlder thread…";
        $out = (new EmailBodyNormalizer)->toTicketHtml($text, 'text');
        $this->assertStringContainsString('Please reset my password', $out);
        $this->assertStringNotContainsString('Older thread', $out);
    }

    public function test_decodes_over_escaped_entities(): void
    {
        $html = '<p>Hello&amp;nbsp;world</p>';
        $out = (new EmailBodyNormalizer)->toTicketHtml($html, 'html');
        $this->assertStringNotContainsString('&amp;nbsp;', $out);
        $this->assertMatchesRegularExpression('/Hello(&nbsp;|\x{00A0}| )world/u', html_entity_decode(strip_tags($out), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
