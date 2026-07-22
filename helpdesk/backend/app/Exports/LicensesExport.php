<?php

namespace App\Exports;

use App\Models\HelpdeskLicense;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LicensesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, HelpdeskLicense>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  HelpdeskLicense  $row
     */
    public function map($row): array
    {
        $expiry = $row->expiry ?? [];

        return [
            $row->name,
            $row->vendor,
            $row->license_key,
            $row->seats_total,
            $row->seats_used,
            optional($row->purchase_date)?->format('Y-m-d'),
            $row->duration_months,
            optional($row->expiry_date)?->format('Y-m-d'),
            $expiry['days_remaining'] ?? null,
            ! empty($expiry['is_expired']) ? 'Yes' : 'No',
            ! empty($expiry['is_expiring_soon']) ? 'Yes' : 'No',
            $row->cost,
            $row->renewal_cost,
            $row->status,
            $row->responsible_person['name'] ?? null,
            $row->notes,
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Vendor',
            'License key',
            'Seats total',
            'Seats used',
            'Purchase date',
            'Duration (months)',
            'Expiry date',
            'Days remaining',
            'Expired',
            'Expiring soon',
            'Cost',
            'Renewal cost',
            'Status',
            'Responsible',
            'Notes',
        ];
    }
}
