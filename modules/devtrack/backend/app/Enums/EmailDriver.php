<?php

namespace App\Enums;

enum EmailDriver: string
{
    case Exchange = 'exchange';
    case Smtp = 'smtp';
    case Ses = 'ses';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::Exchange => 'Microsoft Exchange (Graph API)',
            self::Smtp => 'SMTP',
            self::Ses => 'Amazon SES',
            self::Log => 'Log (development)',
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
