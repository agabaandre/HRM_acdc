{{-- Matrix export PDF: division schedule, approval trail, approver signatures, activities, approved single memos --}}
@php
    // Signature dimensions: width 100px, height 70px. Row height for approval trail 80px. Use pt for mPDF (1px ≈ 0.75pt).
    $SIGNATURE_WIDTH_PX = 100;
    $SIGNATURE_HEIGHT_PX = 70;
    $TRAIL_ROW_HEIGHT_PX = 80;
    $SIG_W_PT = round($SIGNATURE_WIDTH_PX * 0.75, 0);   // 75pt
    $SIG_H_PT = round($SIGNATURE_HEIGHT_PX * 0.75, 0);  // 52pt

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
    .trail-table td { vertical-align: top; }
    .trail-table tbody tr { height: {{ $TRAIL_ROW_HEIGHT_PX }}px; max-height: {{ $TRAIL_ROW_HEIGHT_PX }}px; }
    .approver-name { font-weight: bold; font-size: 10pt; }
    .approver-role { color: #555; font-size: 9pt; }
    .trail-date { font-size: 9pt; color: #555; margin-bottom: 2px; }
    .trail-remarks { font-size: 9pt; margin-top: 4px; padding: 6px; background: #f5f5f5; border-radius: 4px; }
    .signature-cell { width: {{ $SIGNATURE_WIDTH_PX + 12 }}px; padding: 4px; vertical-align: middle; }
    .signature-cell .sig-inner { width: {{ $SIG_W_PT }}pt; border: none; background: transparent; table-layout: fixed; }
    .signature-cell .sig-inner td { width: {{ $SIG_W_PT }}pt; height: {{ $SIG_H_PT }}pt; max-width: {{ $SIG_W_PT }}pt; max-height: {{ $SIG_H_PT }}pt; text-align: center; vertical-align: middle; border: none; padding: 0; overflow: hidden; }
    .signature-cell .signature-image { width: {{ $SIG_W_PT - 2 }}pt !important; height: {{ $SIG_H_PT - 2 }}pt !important; max-width: {{ $SIG_W_PT - 2 }}pt !important; max-height: {{ $SIG_H_PT - 2 }}pt !important; display: block; margin: 0 auto; border: none; }
    .signature-cell .sig-email { font-size: 7pt; color: #555; word-break: break-all; line-height: 1.2; }
    .signature-cell .signature-hash { color: #888; font-size: 6pt; margin-top: 3px; line-height: 1.2; text-align: center; }
    .trail-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9pt; font-weight: bold; }
    .trail-badge.approved, .trail-badge.passed { background: #28a745; color: #fff; }
    .trail-badge.submitted { background: #17a2b8; color: #fff; }
    .trail-badge.returned { background: #fd7e14; color: #fff; }
    .trail-badge.rejected, .trail-badge.flagged { background: #dc3545; color: #fff; }
    .text-muted { color: #666; }
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
            <th style="width: 24%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 12%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 18%; text-align: center;">Fund Type / Budget (Est./Avail.)</th>
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
                <td style="text-align: center;">{{ ucfirst($activity->overall_status ?? 'pending') }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No activities.</td></tr>
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
            <th style="width: 24%;">Title</th>
            <th style="width: 12%;">Date Range</th>
            <th style="width: 14%;">Responsible Person</th>
            <th style="width: 8%; text-align: center;">Participants</th>
            <th style="width: 18%; text-align: center;">Fund Type / Budget (Est./Avail.)</th>
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
                <td style="text-align: center;">{{ ucfirst($memo->overall_status ?? 'approved') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- 3. Matrix Approval Trail --}}
<div class="section-title">Approval Trail & Approver Signatures</div>
<table class="export-table trail-table">
    <thead>
        <tr>
            <th style="width: 32px;">#</th>
            <th style="width: {{ $SIGNATURE_WIDTH_PX + 12 }}px;">Signature</th>
            <th style="width: 125px;">Date & Time</th>
            <th>Approver Name (Role)</th>
            <th style="width: 95px;">Action</th>
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
                $actionLower = strtolower($trail->action ?? '');
                $isFocalOrSubmitter = ($actionLower === 'submitted' || stripos($roleName, 'Focal') !== false);
                $badgeClass = in_array($actionLower, ['approved', 'passed']) ? 'approved' : (in_array($actionLower, ['rejected', 'flagged']) ? 'rejected' : ($actionLower === 'submitted' ? 'submitted' : 'returned'));
                $staffIdForHash = $trail->oic_staff_id ?? $trail->staff_id;
                $signaturePath = !$isFocalOrSubmitter && $approver && !empty(trim($approver->signature ?? '')) ? $baseUrl . '/uploads/staff/signature/' . $approver->signature : null;
                $approverEmail = $approver && !empty(trim($approver->work_email ?? $approver->email ?? '')) ? trim($approver->work_email ?? $approver->email ?? '') : null;
                $approvalDate = $trail->created_at ? $trail->created_at->format('j F Y g:i a') : '—';
                $verifyHash = $matrix && $staffIdForHash ? \App\Helpers\PrintHelper::generateVerificationHash($matrix->id, $staffIdForHash, $trail->created_at) : 'N/A';
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td class="signature-cell">
                    @php
                        $imgWpt = $SIG_W_PT - 2;
                        $imgHpt = $SIG_H_PT - 2;
                    @endphp
                    <table class="sig-inner" cellpadding="0" cellspacing="0" style="width:{{ $SIG_W_PT }}pt; border:none;"><tr><td style="width:{{ $SIG_W_PT }}pt; height:{{ $SIG_H_PT }}pt; overflow:hidden; border:none;">
                        @if($isFocalOrSubmitter)
                            <span class="text-muted" style="font-size: 8pt;">—</span>
                        @elseif($signaturePath)
                            <img class="signature-image" src="{{ $signaturePath }}" alt="Signature" style="width:{{ $imgWpt }}pt !important; height:{{ $imgHpt }}pt !important; max-width:{{ $imgWpt }}pt !important; max-height:{{ $imgHpt }}pt !important; border:none;" />
                        @else
                            <span class="sig-email">{{ $approverEmail ? e($approverEmail) : '—' }}</span>
                        @endif
                    </td></tr></table>
                    <div class="signature-hash">Verify: {{ $verifyHash }}</div>
                </td>
                <td class="trail-date">{{ $approvalDate }}</td>
                <td>
                    <span class="approver-name">{{ $approverName }}</span>
                    <span class="approver-role"> ({{ $roleName }})</span>
                </td>
                <td><span class="trail-badge {{ $badgeClass }}">{{ ucfirst($trail->action ?? '') }}</span></td>
                <td class="trail-remarks">{{ $trail->remarks ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No approval trail.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 4. Participants Schedule (last) --}}
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
