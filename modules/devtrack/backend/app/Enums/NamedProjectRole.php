<?php

namespace App\Enums;

enum NamedProjectRole: string
{
    case Sponsor = 'sponsor';
    case ProductOwner = 'product_owner';
    case ProjectManager = 'project_manager';
    case TechnicalLead = 'technical_lead';
    case SecurityChampion = 'security_champion';
    case ApplicationOwner = 'application_owner';

    public function label(): string
    {
        return match ($this) {
            self::Sponsor => 'Sponsor',
            self::ProductOwner => 'Product owner',
            self::ProjectManager => 'Project manager',
            self::TechnicalLead => 'Technical lead',
            self::SecurityChampion => 'Security champion',
            self::ApplicationOwner => 'Application owner',
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
