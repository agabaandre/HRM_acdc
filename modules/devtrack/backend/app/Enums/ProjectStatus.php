<?php

namespace App\Enums;

enum ProjectStatus: string
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
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::UnderReview => 'Under Review',
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

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isOpen()),
        ));
    }
}
