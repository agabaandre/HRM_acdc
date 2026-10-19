<?php

namespace App\Enums;

enum ReleaseStatus: string
{
    case Proposed = 'proposed';
    case Planned = 'planned';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Planned => 'Planned',
            self::Released => 'Released',
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
