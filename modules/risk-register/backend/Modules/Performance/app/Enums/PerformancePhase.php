<?php

namespace Modules\Performance\Enums;

enum PerformancePhase: string
{
    case Ppa = 'ppa';
    case Midterm = 'midterm';
    case Endterm = 'endterm';

    public function label(): string
    {
        return match ($this) {
            self::Ppa => 'PPA',
            self::Midterm => 'Midterm review',
            self::Endterm => 'End-of-year review',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ppa => 'fa-clipboard-list',
            self::Midterm => 'fa-chart-line',
            self::Endterm => 'fa-flag-checkered',
        };
    }

    public function trailTable(): string
    {
        return match ($this) {
            self::Ppa => 'ppa_approval_trail',
            self::Midterm => 'ppa_approval_trail_midterm',
            self::Endterm => 'ppa_approval_trail_end_term',
        };
    }

    public function draftStatusColumn(): string
    {
        return match ($this) {
            self::Ppa => 'draft_status',
            self::Midterm => 'midterm_draft_status',
            self::Endterm => 'endterm_draft_status',
        };
    }
}
