<?php

namespace App\Support;

final class HelpdeskMailBranding
{
    public static function brandName(): string
    {
        $name = config('helpdesk.mail_brand_name');

        return is_string($name) && $name !== '' ? $name : 'Africa CDC Service Desk';
    }

    public static function logoUrl(): string
    {
        $configured = config('helpdesk.mail_logo_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $base = rtrim((string) config('helpdesk.staff_portal_url', ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('helpdesk.staff_api.base_url', ''), '/');
        }
        if ($base !== '') {
            return $base.'/assets/images/AU_CDC_Logo-800.png';
        }

        return 'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png';
    }

    public static function primaryColor(): string
    {
        return '#119A48';
    }

    public static function primaryDarkColor(): string
    {
        return '#0d7a3a';
    }
}
