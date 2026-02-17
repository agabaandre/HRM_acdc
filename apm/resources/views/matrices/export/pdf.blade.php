{{-- Matrix export PDF: division schedule, approval trail, approver signatures, activities, approved single memos --}}
@php
    // Signature dimensions: 100x30px (aligned with activities print). mPDF uses mm for reliable sizing.
    $SIGNATURE_WIDTH_PX = 100;
    $SIGNATURE_HEIGHT_PX = 30;
    $SIG_W_MM = round(100 * 25.4 / 96, 1);  // 100px at 96dpi ≈ 26.5mm
    $SIG_H_MM = round(30 * 25.4 / 96, 1);   // 30px at 96dpi ≈ 7.9mm

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
    .approver-name { font-weight: bold; font-size: 10pt; }
    .approver-role { color: #555; font-size: 9pt; }
    .trail-date { font-size: 9pt; color: #555; margin-bottom: 2px; }
    .trail-remarks { font-size: 9pt; margin-top: 4px; padding: 6px; background: #f5f5f5; border-radius: 4px; }
    .signature-cell { width: {{ $SIGNATURE_WIDTH_PX + 12 }}px; padding: 4px; vertical-align: middle; }
    .signature-cell .sig-inner { width: {{ $SIGNATURE_WIDTH_PX }}px; border: none; background: transparent; table-layout: fixed; }
    .signature-cell .sig-inner td { width: {{ $SIGNATURE_WIDTH_PX }}px; height: {{ $SIGNATURE_HEIGHT_PX }}px; max-width: {{ $SIGNATURE_WIDTH_PX }}px; max-height: {{ $SIGNATURE_HEIGHT_PX }}px; text-align: center; vertical-align: middle; border: none; padding: 0; overflow: hidden; }
    .signature-cell .signature-image { width: {{ $SIGNATURE_WIDTH_PX - 2 }}px !important; height: {{ $SIGNATURE_HEIGHT_PX - 2 }}px !important; max-width: {{ $SIGNATURE_WIDTH_PX - 2 }}px !important; max-height: {{ $SIGNATURE_HEIGHT_PX - 2 }}px !important; object-fit: contain; display: block; margin: 0 auto; border: none; }
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

<div class="matrix-header">
    <h1>{{ $matrix->title ?? 'Matrix' }} – {{ strtoupper($matrix->quarter ?? '') }} {{ $matrix->year ?? '' }}</h1>
    <div class="meta">{{ $matrix->division->division_name ?? 'Division' }} | Exported {{ now()->format('d F Y H:i') }}</div>
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
                $badgeClass = in_array($actionLower, ['approved', 'passed']) ? 'approved' : (in_array($actionLower, ['rejected', 'flagged']) ? 'rejected' : ($actionLower === 'submitted' ? 'submitted' : 'returned'));
                $staffIdForHash = $trail->oic_staff_id ?? $trail->staff_id;
                $signaturePath = $approver && !empty(trim($approver->signature ?? '')) ? $baseUrl . '/uploads/staff/signature/' . $approver->signature : null;
                $approverEmail = $approver && !empty(trim($approver->work_email ?? $approver->email ?? '')) ? trim($approver->work_email ?? $approver->email ?? '') : null;
                $approvalDate = $trail->created_at ? $trail->created_at->format('j F Y g:i a') : '—';
                $verifyHash = $matrix && $staffIdForHash ? \App\Helpers\PrintHelper::generateVerificationHash($matrix->id, $staffIdForHash, $trail->created_at) : 'N/A';
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td class="signature-cell">
                    @php
                        $imgWmm = round(($SIGNATURE_WIDTH_PX - 2) * 25.4 / 96, 1);
                        $imgHmm = round(($SIGNATURE_HEIGHT_PX - 2) * 25.4 / 96, 1);
                    @endphp
                    <table class="sig-inner" cellpadding="0" cellspacing="0" style="width:{{ $SIG_W_MM }}mm; border:none;"><tr><td style="width:{{ $SIG_W_MM }}mm; height:{{ $SIG_H_MM }}mm; overflow:hidden; border:none;">
                        @if($signaturePath)
                            <img class="signature-image" src="{{ $signaturePath }}" alt="Signature" width="{{ $imgWmm }}" height="{{ $imgHmm }}" style="width:{{ $imgWmm }}mm !important; height:{{ $imgHmm }}mm !important; max-width:{{ $imgWmm }}mm !important; max-height:{{ $imgHmm }}mm !important; border:none;" />
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
