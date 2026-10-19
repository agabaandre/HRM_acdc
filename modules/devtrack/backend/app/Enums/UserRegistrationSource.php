<?php

namespace App\Enums;

enum UserRegistrationSource: string
{
    case Public = 'public';
    case System = 'system';
    case StaffSso = 'staff_sso';
    case MicrosoftSso = 'microsoft_sso';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public registration',
            self::System => 'System user',
            self::StaffSso => 'Africa CDC Staff SSO',
            self::MicrosoftSso => 'Microsoft 365 SSO',
        };
    }
}
