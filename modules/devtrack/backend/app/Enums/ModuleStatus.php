<?php

namespace App\Enums;

enum ModuleStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::UnderReview => 'Under Review',
            self::Completed => 'Completed',
        };
    }

    public function shortLabel(): string
    {
        return $this->label();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
