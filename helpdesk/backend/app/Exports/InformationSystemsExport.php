<?php

namespace App\Exports;

use App\Models\HelpdeskInformationSystem;
use App\Support\InformationSystemStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InformationSystemsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, HelpdeskInformationSystem>  $rows
     * @param  array<int, string>  $divisionNames
     */
    public function __construct(
        private Collection $rows,
        private array $divisionNames = [],
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  HelpdeskInformationSystem  $row
     */
    public function map($row): array
    {
        $divId = (int) ($row->division_id ?? 0);

        return [
            $row->name,
            $row->description,
            InformationSystemStatus::label((string) $row->status),
            $row->version,
            $divId > 0 ? ($this->divisionNames[$divId] ?? ('#'.$divId)) : 'All',
            $row->languages->pluck('name')->implode(', '),
            $row->modules_count ?? $row->modules()->count(),
            $row->focal_name_raw,
            $row->mis_focal_name_raw,
            $row->host,
            $row->domain,
            $row->system_profile_url,
            $row->user_manual_users_url,
            $row->user_manual_managers_url,
            $row->user_manual_technical_url,
        ];
    }

    public function headings(): array
    {
        return [
            'System Name',
            'Description',
            'Status',
            'Version',
            'Division',
            'Languages',
            'Modules',
            'Focal Person',
            'MIS Focal Person',
            'Host',
            'Domain',
            'System Profile URL',
            'User Manual (Users)',
            'User Manual (Managers)',
            'User Manual (Technical)',
        ];
    }
}
