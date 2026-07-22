<?php

namespace App\Exports;

use App\Models\HelpdeskItAsset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItAssetsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, HelpdeskItAsset>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  HelpdeskItAsset  $row
     */
    public function map($row): array
    {
        $valuation = $row->valuation ?? [];

        return [
            $row->asset_tag,
            $row->name,
            $row->category?->name,
            $row->brandRelation?->name ?? $row->brand,
            $row->model,
            $row->serial_number,
            $row->status,
            $row->assigned_name,
            $row->location,
            optional($row->purchase_date)?->format('Y-m-d'),
            $row->purchase_cost,
            $valuation['current_value'] ?? null,
            $row->resolvedUsefulLifeYears(),
            $row->notes,
        ];
    }

    public function headings(): array
    {
        return [
            'Asset tag',
            'Name',
            'Category',
            'Brand',
            'Model',
            'Serial number',
            'Status',
            'Assigned to',
            'Location',
            'Purchase date',
            'Purchase cost',
            'Current value',
            'Useful life (years)',
            'Notes',
        ];
    }
}
