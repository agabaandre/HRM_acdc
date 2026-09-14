<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DivisionsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected Collection $divisions
    ) {}

    public function collection(): Collection
    {
        return $this->divisions;
    }

    public function headings(): array
    {
        return [
            '#',
            'Division Name',
            'Short Name',
            'Category',
            'Division Head',
            'Focal Person',
            'Admin Assistant',
            'Finance Officer',
        ];
    }

    public function map($division): array
    {
        $divisionHead = $division->divisionHead
            ? trim(($division->divisionHead->fname ?? '') . ' ' . ($division->divisionHead->lname ?? ''))
            : 'N/A';
        $focalPerson = $division->focalPerson
            ? trim(($division->focalPerson->fname ?? '') . ' ' . ($division->focalPerson->lname ?? ''))
            : 'N/A';
        $adminAssistant = $division->adminAssistant
            ? trim(($division->adminAssistant->fname ?? '') . ' ' . ($division->adminAssistant->lname ?? ''))
            : 'N/A';
        $financeOfficer = $division->financeOfficer
            ? trim(($division->financeOfficer->fname ?? '') . ' ' . ($division->financeOfficer->lname ?? ''))
            : 'N/A';

        return [
            $division->id,
            $division->division_name,
            $division->division_short_name ?? '',
            $division->category ?? '',
            $divisionHead,
            $focalPerson,
            $adminAssistant,
            $financeOfficer,
        ];
    }
}
