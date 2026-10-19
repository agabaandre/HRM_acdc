<?php

namespace App\Enums;

enum WorkItemPriority: string
{
    case MustDo = 'must_do';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::MustDo => 'Must do',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
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
