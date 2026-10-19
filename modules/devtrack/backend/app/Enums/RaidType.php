<?php

namespace App\Enums;

enum RaidType: string
{
    case Risk = 'risk';
    case Assumption = 'assumption';
    case Issue = 'issue';
    case Dependency = 'dependency';

    public function label(): string
    {
        return match ($this) {
            self::Risk => 'Risk',
            self::Assumption => 'Assumption',
            self::Issue => 'Issue',
            self::Dependency => 'Dependency',
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
