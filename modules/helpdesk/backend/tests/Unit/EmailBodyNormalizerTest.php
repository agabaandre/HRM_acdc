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

    public function test_strips_outlook_signature_container_and_codetwo_images(): void
    {
        $html = <<<'HTML'
<p>Test email tickets</p>
<div id="Signature">
<p><b>Agaba Andrew</b><br>Software Developer<br>Africa Centres for Disease Control and Prevention<br>
Ring Road, 16/17, Haile Garment Square, P.O. Box 3243, Addis Ababa, Ethiopia<br>
Email: AndrewA@africacdc.org Tel:<br>
<a href="https://www.africacdc.org">www.africacdc.org</a> AfricaCDC</p>
<img src="cid:C2_signature_facebook_11560c04-a2fe-43e8-b56d-aab6d2b04dfc.png" alt="Facebook">
<img src="cid:C2_signature_youtube_c6691c84-007a-493d-97ee-5c9196a1ec63.png">
<img src="cid:C2_signature_emailbanner-02_f69248d2-b624-4cd3-a187-207e1ac42140.jpg">
</div>
HTML;

        $out = (new EmailBodyNormalizer)->toTicketHtml($html, 'html');
        $this->assertStringContainsString('Test email tickets', $out);
        $this->assertStringNotContainsString('Agaba Andrew', $out);
        $this->assertStringNotContainsString('Haile Garment', $out);
        $this->assertStringNotContainsString('C2_signature_', $out);
        $this->assertStringNotContainsString('emailbanner', $out);
        $this->assertStringNotContainsString('www.africacdc.org', $out);
    }

    public function test_strips_unwrapped_html_signature_when_outlook_container_is_missing(): void
    {
        $html = <<<'HTML'
<p>Test email tickets</p>
<p><b>Agaba Andrew</b><br>Software Developer<br>Africa Centres for Disease Control and Prevention<br>
Ring Road, 16/17, Haile Garment Square, P.O. Box 3243, Addis Ababa, Ethiopia<br>
<a href="https://www.africacdc.org">www.africacdc.org</a></p>
<img src="cid:C2_signature_facebook_11560c04-a2fe-43e8-b56d-aab6d2b04dfc.png">
<img src="cid:C2_signature_emailbanner-02_f69248d2-b624-4cd3-a187-207e1ac42140.jpg">
HTML;

        $out = (new EmailBodyNormalizer)->toTicketHtml($html, 'html');
        $this->assertStringContainsString('Test email tickets', $out);
        $this->assertStringNotContainsString('Agaba Andrew', $out);
        $this->assertStringNotContainsString('Haile Garment', $out);
        $this->assertStringNotContainsString('C2_signature_', $out);
    }

    public function test_strips_plain_text_org_signature_after_the_message(): void
    {
        $text = "Test\n\nAgaba Andrew\nSoftware Developer\nAfrica Centres for Disease Control and Prevention\nRing Road, 16/17, Haile Garment Square, P.O. Box 3243, Addis Ababa, Ethiopia\nEmail: AndrewA@africacdc.org Tel:\nwww.africacdc.org AfricaCDC";
        $out = (new EmailBodyNormalizer)->toTicketHtml($text, 'text');
        $plain = html_entity_decode(strip_tags($out), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Test', $plain);
        $this->assertStringNotContainsString('Agaba Andrew', $plain);
        $this->assertStringNotContainsString('Haile Garment', $plain);
        $this->assertStringNotContainsString('africacdc.org', $plain);
    }

    public function test_keeps_a_message_that_mentions_africa_cdc_without_a_signature_block(): void
    {
        $html = '<p>Please update the Africa CDC VPN client on my laptop.</p>';
        $out = (new EmailBodyNormalizer)->toTicketHtml($html, 'html');
        $this->assertStringContainsString('Africa CDC VPN client', $out);
    }
}
