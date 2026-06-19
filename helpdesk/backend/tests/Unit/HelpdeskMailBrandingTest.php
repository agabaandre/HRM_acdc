<?php

namespace Tests\Unit;

use App\Support\HelpdeskMailBranding;
use Tests\TestCase;

class HelpdeskMailBrandingTest extends TestCase
{
    public function test_default_brand_name_is_africa_cdc_helpdesk(): void
    {
        config(['helpdesk.mail_brand_name' => 'Africa CDC Helpdesk']);

        $this->assertSame('Africa CDC Helpdesk', HelpdeskMailBranding::brandName());
    }

    public function test_logo_url_uses_staff_portal_cbp_assets_by_default(): void
    {
        config([
            'helpdesk.mail_logo_url' => null,
            'helpdesk.staff_portal_url' => 'https://cbp.africacdc.org/staff',
        ]);

        $this->assertSame(
            'https://cbp.africacdc.org/staff/cbp-assets/images/AU_CDC_Logo-800.png',
            HelpdeskMailBranding::logoUrl()
        );
    }
}
