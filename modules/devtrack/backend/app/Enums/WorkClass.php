<?php

namespace App\Enums;

enum WorkClass: string
{
    case Incident = 'incident';
    case ServiceRequest = 'service_request';
    case MinorEnhancement = 'minor_enhancement';
    case MediumProject = 'medium_project';
    case MajorProject = 'major_project';
    case Research = 'research';

    public function label(): string
    {
        return match ($this) {
            self::Incident => 'Incident / break-fix',
            self::ServiceRequest => 'Standard service request',
            self::MinorEnhancement => 'Minor enhancement',
            self::MediumProject => 'Medium project',
            self::MajorProject => 'Major strategic project',
            self::Research => 'Research / proof of concept',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Incident => 'A',
            self::ServiceRequest => 'B',
            self::MinorEnhancement => 'C',
            self::MediumProject => 'D',
            self::MajorProject => 'E',
            self::Research => 'F',
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
