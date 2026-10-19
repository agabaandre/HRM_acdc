<?php

namespace App\Enums;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case Health = 'health';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
            self::Confidential => 'Confidential',
            self::Restricted => 'Restricted',
            self::Health => 'Health / personal data',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
