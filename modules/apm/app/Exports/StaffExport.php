<?php

namespace App\Exports;

use App\Models\Staff;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StaffExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Staff::with(['division', 'dutyStation']);

        // Apply filters
        if (isset($this->filters['global_search']) && !empty($this->filters['global_search'])) {
            $search = $this->filters['global_search'];
            $query->where(function ($q) use ($search) {
                $q->where('staff_id', 'like', "%{$search}%")
                  ->orWhere('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('tel_1', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('division', function($q) use ($search) {
                      $q->where('division_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('directorate', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('dutyStation', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (isset($this->filters['division_id']) && !empty($this->filters['division_id'])) {
            $query->where('division_id', $this->filters['division_id']);
        }

        if (isset($this->filters['directorate_id']) && !empty($this->filters['directorate_id'])) {
            $query->where('directorate_id', $this->filters['directorate_id']);
        }

        if (isset($this->filters['duty_station_id']) && !empty($this->filters['duty_station_id'])) {
            $query->where('duty_station_id', $this->filters['duty_station_id']);
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== '') {
            $query->where('active', $this->filters['status']);
        }

        return $query->orderBy('fname');
    }

    public function headings(): array
    {
        return [
            'Staff ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Division',
            'Duty Station',
            'Job Title',
            'Designation',
            'Employment Status',
            'Gender',
            'Date of Birth',
            'Hire Date',
            'Status',
            'Created At'
        ];
    }

    public function map($staff): array
    {
        return [
            $staff->staff_id,
            $staff->fname,
            $staff->lname,
            $staff->email,
            $staff->tel_1,
            $staff->division->division_name ?? 'N/A',
            $staff->dutyStation->name ?? 'N/A',
            $staff->title,
            $staff->designation ?? 'N/A',
            $staff->employment_status ?? 'N/A',
            $staff->gender ?? 'N/A',
            $staff->dob ? \Carbon\Carbon::parse($staff->dob)->format('Y-m-d') : 'N/A',
            $staff->hire_date ? \Carbon\Carbon::parse($staff->hire_date)->format('Y-m-d') : 'N/A',
            $staff->active ? 'Active' : 'Inactive',
            $staff->created_at ? \Carbon\Carbon::parse($staff->created_at)->format('Y-m-d H:i:s') : 'N/A'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Staff ID
            'B' => 20, // First Name
            'C' => 20, // Last Name
            'D' => 30, // Email
            'E' => 20, // Phone
            'F' => 25, // Division
            'G' => 25, // Duty Station
            'H' => 30, // Job Title
            'I' => 25, // Designation
            'J' => 20, // Employment Status
            'K' => 10, // Gender
            'L' => 15, // Date of Birth
            'M' => 15, // Hire Date
            'N' => 10, // Status
            'O' => 20, // Created At
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '667eea']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Add borders to all cells
                $sheet->getStyle('A1:O' . $sheet->getHighestRow())
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Auto-fit columns
                foreach (range('A', 'O') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Add title
                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells('A1:O1');
                $sheet->setCellValue('A1', 'STAFF DIRECTORY EXPORT');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('667eea');
                $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');

                // Add export date
                $sheet->setCellValue('A2', 'Exported on: ' . \Carbon\Carbon::now()->format('Y-m-d H:i:s'));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFont()->setItalic(true);

                // Adjust row heights
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(3)->setRowHeight(25);
            },
        ];
    }
}
