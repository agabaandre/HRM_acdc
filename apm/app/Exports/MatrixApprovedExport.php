<?php

namespace App\Exports;

use App\Models\Matrix;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MatrixApprovedExport implements WithMultipleSheets
{
    public function __construct(
        public Matrix $matrix,
        public $divisionStaff,
        public $activities,
        public $approvedSingleMemos
    ) {}

    public function sheets(): array
    {
        return [
            'Division Schedule' => new MatrixDivisionScheduleSheet($this->divisionStaff),
            'Approval Trail' => new MatrixApprovalTrailSheet($this->matrix),
            'Activities' => new MatrixActivitiesSheet($this->activities),
            'Single Memos' => new MatrixSingleMemosSheet($this->approvedSingleMemos),
        ];
    }
}

class MatrixDivisionScheduleSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public $divisionStaff) {}

    public function collection(): Collection
    {
        $rows = collect();
        $list = $this->divisionStaff instanceof Collection ? $this->divisionStaff : collect($this->divisionStaff ?? []);
        foreach ($list as $idx => $staff) {
            $divDays = (int) ($staff->division_days ?? 0);
            $otherDays = (int) ($staff->other_days ?? 0);
            $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            $rows->push([
                $idx + 1,
                $name ?: 'N/A',
                $staff->job_name ?? $staff->title ?? 'N/A',
                $divDays,
                $otherDays,
                $divDays + $otherDays,
            ]);
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['#', 'Staff Name', 'Position', 'Division Days', 'Other Divisions', 'Total Days'];
    }

    public function title(): string
    {
        return 'Division Schedule';
    }
}

class MatrixApprovalTrailSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public Matrix $matrix) {}

    public function collection(): Collection
    {
        $rows = collect();
        $trails = $this->matrix->matrixApprovalTrails ?? collect();
        $trails = $trails->sortByDesc('created_at')->values();
        foreach ($trails as $idx => $trail) {
            $approver = $trail->oicStaff ?? $trail->staff;
            $approverName = $approver ? trim(($approver->title ?? '') . ' ' . ($approver->fname ?? '') . ' ' . ($approver->lname ?? '') . ' ' . ($approver->oname ?? '')) : 'N/A';
            $rows->push([
                $idx + 1,
                $approverName,
                $trail->approver_role_name ?? 'Focal Person',
                ucfirst($trail->action ?? ''),
                $trail->created_at ? $trail->created_at->format('d M Y H:i') : '—',
                $trail->remarks ?? '—',
            ]);
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['#', 'Approver Name', 'Role', 'Action', 'Date & Time', 'Remarks'];
    }

    public function title(): string
    {
        return 'Approval Trail';
    }
}

class MatrixActivitiesSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public $activities) {}

    public function collection(): Collection
    {
        $rows = collect();
        $list = $this->activities instanceof Collection ? $this->activities : collect($this->activities ?? []);
        foreach ($list as $idx => $activity) {
            $budget = $this->budgetTotal($activity->budget_breakdown ?? null);
            $resp = $activity->responsiblePerson ?? null;
            $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
            $dateFrom = $activity->date_from ? \Carbon\Carbon::parse($activity->date_from)->format('d M Y') : '—';
            $dateTo = $activity->date_to ? \Carbon\Carbon::parse($activity->date_to)->format('d M Y') : '—';
            $fundType = $activity->fundType->name ?? 'N/A';
            $avail = isset($activity->available_budget) && $activity->available_budget !== null ? number_format((float) $activity->available_budget, 2) . ' USD' : '';
            $rows->push([
                $idx + 1,
                $activity->document_number ?? 'N/A',
                $activity->activity_title ?? '—',
                $dateFrom . ' to ' . $dateTo,
                $respName,
                $activity->total_participants ?? 0,
                $fundType,
                number_format($budget, 2) . ' USD' . ($avail ? " (Avail: $avail)" : ''),
                ucfirst($activity->overall_status ?? 'pending'),
            ]);
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['#', 'Document #', 'Title', 'Date Range', 'Responsible Person', 'Participants', 'Funding', 'Budget (Est./Avail.)', 'Status'];
    }

    public function title(): string
    {
        return 'Activities';
    }

    private function budgetTotal($breakdown): float
    {
        if (!$breakdown) return 0.0;
        $b = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
        if (!is_array($b)) return 0.0;
        $total = 0.0;
        foreach ($b as $key => $entries) {
            if ($key === 'grand_total') continue;
            if (!is_array($entries)) continue;
            foreach ($entries as $item) {
                $uc = (float) ($item['unit_cost'] ?? 0);
                $u = (float) ($item['units'] ?? 0);
                $d = (float) ($item['days'] ?? 1);
                $total += $d > 1 ? $uc * $u * $d : $uc * $u;
            }
        }
        return $total;
    }
}

class MatrixSingleMemosSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public $approvedSingleMemos) {}

    public function collection(): Collection
    {
        $rows = collect();
        $list = $this->approvedSingleMemos instanceof Collection ? $this->approvedSingleMemos : collect($this->approvedSingleMemos ?? []);
        foreach ($list as $idx => $memo) {
            $budget = $this->budgetTotal($memo->budget_breakdown ?? null);
            $resp = $memo->responsiblePerson ?? null;
            $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
            $dateFrom = $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('d M Y') : '—';
            $dateTo = $memo->date_to ? \Carbon\Carbon::parse($memo->date_to)->format('d M Y') : '—';
            $avail = isset($memo->available_budget) && $memo->available_budget !== null ? number_format((float) $memo->available_budget, 2) . ' USD' : '';
            $rows->push([
                $idx + 1,
                $memo->document_number ?? 'N/A',
                $memo->activity_title ?? '—',
                $dateFrom . ' to ' . $dateTo,
                $respName,
                $memo->total_participants ?? 0,
                $memo->fundType->name ?? 'N/A',
                number_format($budget, 2) . ' USD' . ($avail ? " (Avail: $avail)" : ''),
                ucfirst($memo->overall_status ?? 'approved'),
            ]);
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['#', 'Document #', 'Title', 'Date Range', 'Responsible Person', 'Participants', 'Fund Type', 'Budget (Est./Avail.)', 'Status'];
    }

    public function title(): string
    {
        return 'Single Memos';
    }

    private function budgetTotal($breakdown): float
    {
        if (!$breakdown) return 0.0;
        $b = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
        if (!is_array($b)) return 0.0;
        $total = 0.0;
        foreach ($b as $key => $entries) {
            if ($key === 'grand_total') continue;
            if (!is_array($entries)) continue;
            foreach ($entries as $item) {
                $uc = (float) ($item['unit_cost'] ?? 0);
                $u = (float) ($item['units'] ?? 0);
                $d = (float) ($item['days'] ?? 1);
                $total += $d > 1 ? $uc * $u * $d : $uc * $u;
            }
        }
        return $total;
    }
}
