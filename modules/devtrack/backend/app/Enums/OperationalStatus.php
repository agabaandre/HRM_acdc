<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case Planned = 'planned';
    case Live = 'live';
    case Suspended = 'suspended';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Live => 'Live',
            self::Suspended => 'Suspended',
            self::Retired => 'Retired',
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
