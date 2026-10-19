<?php

namespace App\Enums;

enum EnhancementWorkType: string
{
    case FeatureImprovement = 'feature_improvement';
    case Integration = 'integration';
    case BugFix = 'bug_fix';

    public function label(): string
    {
        return match ($this) {
            self::FeatureImprovement => 'Feature improvement',
            self::Integration => 'Integration',
            self::BugFix => 'Bug fix',
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
