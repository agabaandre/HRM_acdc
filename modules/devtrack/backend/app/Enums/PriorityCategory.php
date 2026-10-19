<?php

namespace App\Enums;

enum PriorityCategory: string
{
    case MustDo = 'must_do';
    case HighPriority = 'high_priority';
    case Planned = 'planned';
    case Parked = 'parked';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::MustDo => 'Must Do',
            self::HighPriority => 'High Priority',
            self::Planned => 'Planned',
            self::Parked => 'Parked',
            self::Declined => 'Declined',
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
