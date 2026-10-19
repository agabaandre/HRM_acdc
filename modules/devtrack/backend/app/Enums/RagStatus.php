<?php

namespace App\Enums;

enum RagStatus: string
{
    case Green = 'green';
    case Amber = 'amber';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Green — under control',
            self::Amber => 'Amber — risk forming',
            self::Red => 'Red — major blocker',
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
