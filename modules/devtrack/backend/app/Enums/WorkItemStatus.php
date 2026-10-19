<?php

namespace App\Enums;

enum WorkItemStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not yet Started',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Completed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
