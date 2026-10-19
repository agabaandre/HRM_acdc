<?php

namespace App\Enums;

enum ProjectStage: string
{
    case Intake = 'intake';
    case Discovery = 'discovery';
    case Initiation = 'initiation';
    case RequirementsDesign = 'requirements_design';
    case Development = 'development';
    case QualityAssurance = 'quality_assurance';
    case UserAcceptance = 'user_acceptance';
    case ProductionRelease = 'production_release';
    case Hypercare = 'hypercare';
    case ContinuousImprovement = 'continuous_improvement';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Request intake',
            self::Discovery => 'Discovery',
            self::Initiation => 'Initiation',
            self::RequirementsDesign => 'Requirements and design',
            self::Development => 'Development',
            self::QualityAssurance => 'Quality assurance',
            self::UserAcceptance => 'User acceptance testing',
            self::ProductionRelease => 'Production release',
            self::Hypercare => 'Hypercare and transition',
            self::ContinuousImprovement => 'Continuous improvement',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Intake => 'Intake',
            self::Discovery => 'Discovery',
            self::Initiation => 'Initiation',
            self::RequirementsDesign => 'Design',
            self::Development => 'Build',
            self::QualityAssurance => 'QA',
            self::UserAcceptance => 'UAT',
            self::ProductionRelease => 'Release',
            self::Hypercare => 'Hypercare',
            self::ContinuousImprovement => 'Improve',
        };
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $cases[$index + 1] ?? null;
    }

    public function approver(): string
    {
        return match ($this) {
            self::Intake => 'Development Team Lead',
            self::Discovery, self::Initiation => 'Sponsor and Product Owner',
            self::RequirementsDesign => 'Product Owner, Technical Lead and Security',
            self::Development => 'Technical Lead',
            self::QualityAssurance => 'QA Lead',
            self::UserAcceptance => 'Product Owner',
            self::ProductionRelease => 'System Owner and change authority',
            self::Hypercare => 'Application / System Owner',
            self::ContinuousImprovement => 'Product Owner and Application Owner',
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
