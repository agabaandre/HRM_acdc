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

    public function test_logo_url_prefers_app_logo_url_env(): void
    {
        config([
            'helpdesk.mail_logo_url' => 'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png',
            'helpdesk.staff_portal_url' => 'https://cbp.africacdc.org/staff',
        ]);

        $this->assertSame(
            'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png',
            HelpdeskMailBranding::logoUrl()
        );
    }

    public function test_logo_url_falls_back_to_staff_portal_assets_path(): void
    {
        config([
            'helpdesk.mail_logo_url' => null,
            'helpdesk.staff_portal_url' => 'https://cbp.africacdc.org/staff',
        ]);

        $this->assertSame(
            'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png',
            HelpdeskMailBranding::logoUrl()
        );
    }
}
