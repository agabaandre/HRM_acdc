<?php

namespace App\Enums;

enum EnhancementStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Completed = 'completed';
    case Accepted = 'accepted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::UnderReview => 'Under Review',
            self::Completed => 'Completed',
            self::Accepted => 'Accepted',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Accepted, self::Closed], true);
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
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isActive()),
        ));
    }
}
