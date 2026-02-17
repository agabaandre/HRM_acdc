{{-- Matrix export PDF: division schedule, approval trail, approver signatures, activities, approved single memos --}}
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
    .trail-table td { vertical-align: middle; }
    .approver-name { font-weight: bold; }
    .trail-date { font-size: 9pt; color: #555; }
    .trail-remarks { font-size: 9pt; margin-top: 4px; }
    .signature-cell { width: 80px; text-align: center; }
    .signature-cell img { max-width: 70px; max-height: 40px; }
    .text-muted { color: #666; }
    .matrix-header { margin-bottom: 16px; }
    .matrix-header h1 { font-size: 14pt; margin: 0 0 4px 0; }
    .matrix-header .meta { font-size: 9pt; color: #666; }
</style>

<div class="matrix-header">
    <h1>{{ $matrix->title ?? 'Matrix' }} – {{ strtoupper($matrix->quarter ?? '') }} {{ $matrix->year ?? '' }}</h1>
    <div class="meta">{{ $matrix->division->division_name ?? 'Division' }} | Exported {{ now()->format('d F Y H:i') }}</div>
</div>

{{-- Division Schedule --}}
<div class="section-title">Division Schedule – {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}</div>
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

{{-- Approval Trail & Approver Signatures --}}
<div class="section-title">Approval Trail & Approver Signatures</div>
<table class="export-table trail-table">
    <thead>
        <tr>
            <th style="width: 40px;">#</th>
            <th style="width: 90px;">Signature / Photo</th>
            <th>Approver Name</th>
            <th>Role</th>
            <th>Action</th>
            <th style="width: 120px;">Date & Time</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @php $trailsSorted = $trails ? $trails->sortByDesc('created_at') : collect(); @endphp
        @forelse($trailsSorted as $idx => $trail)
            @php
                $approver = $trail->oicStaff ?? $trail->staff;
                $approverName = $approver ? trim(($approver->title ?? '') . ' ' . ($approver->fname ?? '') . ' ' . ($approver->lname ?? '') . ' ' . ($approver->oname ?? '')) : 'N/A';
                $roleName = $trail->approver_role_name ?? 'Focal Person';
                $photoUrl = $approver && !empty(trim($approver->photo ?? '')) ? $baseUrl . '/uploads/staff/' . $approver->photo : null;
                $signatureUrl = $approver && !empty(trim($approver->signature ?? '')) ? $baseUrl . '/uploads/staff/' . $approver->signature : null;
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td class="signature-cell">
                    @if($signatureUrl)
                        <img src="{{ $signatureUrl }}" alt="Signature" />
                    @elseif($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Photo" />
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="approver-name">{{ $approverName }}</td>
                <td>{{ $roleName }}</td>
                <td>{{ ucfirst($trail->action ?? '') }}</td>
                <td class="trail-date">{{ $trail->created_at ? $trail->created_at->format('d M Y, H:i') : '—' }}</td>
                <td class="trail-remarks">{{ $trail->remarks ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No approval trail.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Activities (tabular) --}}
<div class="section-title">Activities</div>
<table class="export-table">
    <thead>
        <tr>
            <th style="width: 28px;">#</th>
            <th style="width: 11%;">Document #</th>
            <th style="width: 24%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 12%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 10%; text-align: center;">Funding</th>
            <th style="width: 10%; text-align: center;">Budget (Est./Avail.)</th>
            <th style="width: 8%; text-align: center;">Status</th>
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
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $activity->document_number ?? 'N/A' }}</td>
                <td>{{ $activity->activity_title ?? '—' }}</td>
                <td>{{ $dateFrom }} to {{ $dateTo }}</td>
                <td>{{ $respName }}</td>
                <td style="text-align: center;">{{ $activity->total_participants ?? 0 }}</td>
                <td style="text-align: center;">{{ $fundTypeName }}</td>
                <td style="text-align: center;">{{ number_format($budget, 2) }} USD @if(!empty($activity->available_budget))(Avail: {{ number_format($activity->available_budget, 2) }})@endif</td>
                <td style="text-align: center;">{{ ucfirst($activity->overall_status ?? 'pending') }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No activities.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Approved Single Memos --}}
@if(isset($approvedSingleMemos) && $approvedSingleMemos->count() > 0)
<div class="section-title">Approved Single Memos</div>
<table class="export-table">
    <thead>
        <tr>
            <th style="width: 28px;">#</th>
            <th style="width: 11%;">Document #</th>
            <th style="width: 24%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 14%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 10%; text-align: center;">Fund Type</th>
            <th style="width: 10%; text-align: center;">Budget (Est./Avail.)</th>
            <th style="width: 8%; text-align: center;">Status</th>
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
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $memo->document_number ?? 'N/A' }}</td>
                <td>{{ $memo->activity_title ?? '—' }}</td>
                <td>{{ $dateFrom }} to {{ $dateTo }}</td>
                <td>{{ $respName }}</td>
                <td style="text-align: center;">{{ $memo->total_participants ?? 0 }}</td>
                <td style="text-align: center;">{{ $memo->fundType->name ?? 'N/A' }}</td>
                <td style="text-align: center;">{{ number_format($budget, 2) }} USD @if(!empty($memo->available_budget))(Avail: {{ number_format($memo->available_budget, 2) }})@endif</td>
                <td style="text-align: center;">{{ ucfirst($memo->overall_status ?? 'approved') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
