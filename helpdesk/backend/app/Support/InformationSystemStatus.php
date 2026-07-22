<?php

namespace App\Support;

final class InformationSystemStatus
{
    public const TO_BE_DEVELOPED = 'to_be_developed';

    public const IN_DEVELOPMENT = 'in_development';

    public const UNDER_TESTING = 'under_testing';

    public const IN_USE = 'in_use';

    public const DECOMMISSIONED = 'decommissioned';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TO_BE_DEVELOPED,
            self::IN_DEVELOPMENT,
            self::UNDER_TESTING,
            self::IN_USE,
            self::DECOMMISSIONED,
        ];
    }

    public static function fromExcel(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));

        return match ($key) {
            'active' => self::IN_USE,
            'developed' => self::UNDER_TESTING,
            'not yet developed', '' => self::TO_BE_DEVELOPED,
            default => in_array($key, self::all(), true) ? $key : self::TO_BE_DEVELOPED,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::TO_BE_DEVELOPED => 'To be Developed',
            self::IN_DEVELOPMENT => 'In development',
            self::UNDER_TESTING => 'Under Testing',
            self::IN_USE => 'In Use',
            self::DECOMMISSIONED => 'Decommissioned',
            default => $status,
        };
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
