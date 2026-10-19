<?php

namespace App\Enums;

enum RaidStatus: string
{
    case Open = 'open';
    case Mitigating = 'mitigating';
    case Resolved = 'resolved';
    case Accepted = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Mitigating => 'Mitigating',
            self::Resolved => 'Resolved',
            self::Accepted => 'Accepted',
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
