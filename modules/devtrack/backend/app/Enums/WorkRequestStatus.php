<?php

namespace App\Enums;

enum WorkRequestStatus: string
{
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Classified = 'classified';
    case Converted = 'converted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Classified => 'Classified',
            self::Converted => 'Converted to project',
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
