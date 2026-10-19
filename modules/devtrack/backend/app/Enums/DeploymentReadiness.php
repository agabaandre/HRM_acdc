<?php

namespace App\Enums;

enum DeploymentReadiness: string
{
    case NotReady = 'not_ready';
    case InProgress = 'in_progress';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::NotReady => 'Not ready',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
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
