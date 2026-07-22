<?php

namespace App\Exports;

use App\Models\HelpdeskSoftwareRequest;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SoftwareRequestsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, HelpdeskSoftwareRequest>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  HelpdeskSoftwareRequest  $row
     */
    public function map($row): array
    {
        return [
            $row->request_number,
            $row->request_title,
            $row->status,
            $row->decision,
            $row->priority,
            $row->requester_name,
            $row->email,
            $row->department,
            $row->division_name,
            $row->directorate_name,
            $row->desired_timeline,
            $row->budget_estimate,
            optional($row->received_at)?->toIso8601String(),
            $row->assigned_ba_name,
            optional($row->created_at)?->toIso8601String(),
            optional($row->updated_at)?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'Request #',
            'Title',
            'Status',
            'Decision',
            'Priority',
            'Requester',
            'Email',
            'Department',
            'Division',
            'Directorate',
            'Desired timeline',
            'Budget estimate',
            'Received at',
            'Assigned BA',
            'Created at',
            'Updated at',
        ];
    }
}
