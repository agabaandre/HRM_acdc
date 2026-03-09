{{-- Matrix export PDF: division schedule, activities, approved single memos, participants schedule --}}
@php
    if (!function_exists('_matrix_export_budget_total')) {
        function _matrix_export_budget_total($breakdown) {
            if (!$breakdown) return 0;
            $b = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
            if (!is_array($b)) return 0;
            $total = 0;
            foreach ($b as $key => $entries) {
                if ($key === 'grand_total') continue;
                if (!is_array($entries)) continue;
                foreach ($entries as $item) {
                    $uc = (float)($item['unit_cost'] ?? 0);
                    $u = (float)($item['units'] ?? 0);
                    $d = (float)($item['days'] ?? 1);
                    $total += $d > 1 ? $uc * $u * $d : $uc * $u;
                }
            }
            return $total;
        }
    }
@endphp
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
    .section-title { font-size: 12pt; font-weight: bold; margin: 12px 0 8px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    table.export-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.export-table th, table.export-table td { border: 1px solid #999; padding: 5px 6px; text-align: left; }
    table.export-table th { background: #f0f0f0; font-weight: bold; }
    table.export-table td { vertical-align: top; }
    .matrix-header { margin-bottom: 16px; }
    .matrix-header h1 { font-size: 14pt; margin: 0 0 4px 0; }
    .matrix-header .meta { font-size: 9pt; color: #666; }
</style>

@php
    $matrixDocNumber = $matrix->document_number ?? ('QM/' . ($matrix->year ?? '') . '/' . ($matrix->quarter ?? ''));
    $matrixShowUrl = $matrixShowUrl ?? (rtrim($baseUrl ?? '', '/') . '/apm/matrices/' . $matrix->id);
@endphp
<div class="matrix-header">
    <h1>@if(!empty($matrixShowUrl))<a href="{{ $matrixShowUrl }}" style="color: #000; text-decoration: none;">@endif{{ $matrix->title ?? 'Matrix' }} – {{ strtoupper($matrix->quarter ?? '') }} {{ $matrix->year ?? '' }}@if(!empty($matrixShowUrl))</a>@endif</h1>
    <div class="meta">{{ $matrix->division->division_name ?? 'Division' }} | Matrix Document Number / File Number: {{ $matrixDocNumber }} | Exported {{ now()->format('d F Y H:i') }}</div>
</div>

{{-- 1. Activities (first) --}}
<div class="section-title">Activities</div>
<table class="export-table">
    <thead>
        <tr>
            <th style="width: 28px;">#</th>
            <th style="width: 11%;">Document #</th>
            <th style="width: 26%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 14%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 21%; text-align: center;">Fund Type / Budget (Est./Avail.)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($activities ?? [] as $idx => $activity)
            @php
                $budget = _matrix_export_budget_total($activity->budget_breakdown ?? null);
                $resp = $activity->responsiblePerson;
                $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
                $fundTypeName = $activity->fundType->name ?? 'N/A';
                $dateFrom = $activity->date_from ? \Carbon\Carbon::parse($activity->date_from)->format('d M Y') : '—';
                $dateTo = $activity->date_to ? \Carbon\Carbon::parse($activity->date_to)->format('d M Y') : '—';
                $fundAndBudget = $fundTypeName . ' | ' . number_format($budget, 2) . ' USD' . (!empty($activity->available_budget) ? ' (Avail: ' . number_format($activity->available_budget, 2) . ')' : '');
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $activity->document_number ?? 'N/A' }}</td>
                <td>{{ $activity->activity_title ?? '—' }}</td>
                <td>{{ $dateFrom }} to {{ $dateTo }}</td>
                <td>{{ $respName }}</td>
                <td style="text-align: center;">{{ $activity->total_participants ?? 0 }}</td>
                <td style="text-align: center;">{{ $fundAndBudget }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No activities.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2. Single Memos --}}
@if(isset($approvedSingleMemos) && $approvedSingleMemos->count() > 0)
<div class="section-title">Approved Single Memos</div>
<table class="export-table">
    <thead>
        <tr>
            <th style="width: 28px;">#</th>
            <th style="width: 11%;">Document #</th>
            <th style="width: 26%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 14%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 21%; text-align: center;">Fund Type / Budget (Est./Avail.)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($approvedSingleMemos as $idx => $memo)
            @php
                $budget = _matrix_export_budget_total($memo->budget_breakdown ?? null);
                $resp = $memo->responsiblePerson;
                $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
                $dateFrom = $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('d M Y') : '—';
                $dateTo = $memo->date_to ? \Carbon\Carbon::parse($memo->date_to)->format('d M Y') : '—';
                $fundAndBudget = ($memo->fundType->name ?? 'N/A') . ' | ' . number_format($budget, 2) . ' USD' . (!empty($memo->available_budget) ? ' (Avail: ' . number_format($memo->available_budget, 2) . ')' : '');
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $memo->document_number ?? 'N/A' }}</td>
                <td>{{ $memo->activity_title ?? '—' }}</td>
                <td>{{ $dateFrom }} to {{ $dateTo }}</td>
                <td>{{ $respName }}</td>
                <td style="text-align: center;">{{ $memo->total_participants ?? 0 }}</td>
                <td style="text-align: center;">{{ $fundAndBudget }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- 3. Participants Schedule --}}
<div class="section-title">Participants Schedule – {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}</div>
<table class="export-table">
    <thead>
        <tr>
            <th style="width: 30px;">#</th>
            <th>Staff Name</th>
            <th>Position</th>
            <th style="width: 80px; text-align: center;">Division Days</th>
            <th style="width: 80px; text-align: center;">Other Divisions</th>
            <th style="width: 70px; text-align: center;">Total Days</th>
        </tr>
    </thead>
    <tbody>
        @forelse($divisionStaff ?? [] as $idx => $staff)
            @php
                $divDays = (int) ($staff->division_days ?? 0);
                $otherDays = (int) ($staff->other_days ?? 0);
                $totalDays = $divDays + $otherDays;
                $name = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $name ?: 'N/A' }}</td>
                <td>{{ $staff->job_name ?? $staff->title ?? 'N/A' }}</td>
                <td style="text-align: center;">{{ $divDays }}</td>
                <td style="text-align: center;">{{ $otherDays }}</td>
                <td style="text-align: center;">{{ $totalDays }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No division schedule data.</td></tr>
        @endforelse
    </tbody>
</table>
