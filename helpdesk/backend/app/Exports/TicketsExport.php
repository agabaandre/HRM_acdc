<?php

namespace App\Exports;

use App\Models\HelpdeskTicket;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, HelpdeskTicket>  $tickets
     */
    public function __construct(private Collection $tickets) {}

    public function collection(): Collection
    {
        return $this->tickets;
    }

    /**
     * @param  HelpdeskTicket  $row
     */
    public function map($row): array
    {
        return [
            $row->ticket_number,
            $row->subject,
            $row->category?->name,
            $row->status,
            $row->priority,
            $row->requester_name,
            $row->requester_email,
            $row->assignee?->name,
            optional($row->resolved_at)?->toIso8601String(),
            $row->resolution_summary,
        ];
    }

    public function headings(): array
    {
        return [
            'Ticket #',
            'Subject',
            'Category',
            'Status',
            'Priority',
            'Requester',
            'Requester email',
            'Assignee',
            'Resolved at',
            'Resolution summary',
        ];
    }
}
