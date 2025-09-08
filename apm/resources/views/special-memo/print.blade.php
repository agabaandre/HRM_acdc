<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Special Memo #{{ $specialMemo->id }}</title>
    <style>
        @page { size: A4; margin: 8mm 14mm 8mm 14mm; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #111; font-size: 12px; padding-bottom: 28mm; }
        h1,h2,h3 { margin: 0 0 6px; }
        .title { text-align: center; margin-bottom: 12px; }
        .muted { color: #666; }
        .small { font-size: 11px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-12 { margin-bottom: 12px; }
        .mb-16 { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 6px 8px; vertical-align: top; word-wrap: break-word; }
        th { background: #f1f1f1; text-align: left; }
        .no-border th, .no-border td { border: none; padding: 2px 0; }
        .w-25 { width: 25%; }
        .w-35 { width: 35%; }
        .w-50 { width: 50%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-after: always; }
        .section { margin-bottom: 14px; }
        /* Watermark */
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.04;
            text-align: center;
        }
        /* Footer */
        .footer { color: #4d4d4d; }
        .footer .line { border-top: 1px solid #999; }
        /* Footer */
        .footer {
            position: fixed;
            left: 0; right: 0; bottom: 8mm;
            font-size: 10px; color: #666;
            text-align: center;
        }
        .footer .line { border-top: 1px solid #aaa; margin: 6px 14mm 4px; }
    </style>
</head>
<body>
    <div class="watermark">
        <img src="{{ public_path('assets/images/au_emblem.png') }}" alt="Watermark" style="width:85%; max-width:none;">
    </div>
    <div class="paper-header" style="margin-bottom: 10px;">
        <table class="no-border" style="width:100%;">
            <tr>
                <td style="width: 20%;">
                    <img src="{{ public_path('assets/images/AU_CDC_Logo-800.png') }}" alt="Africa CDC Logo" style="height: 80px;">
                </td>
                <td style="text-align: center;">
                    <div style="font-weight:700; font-size: 14px;">Africa Centres for Disease Control and Prevention (Africa CDC)</div>
                    <div class="small muted">African Union Commission</div>
                    <div class="small muted">Addis Ababa, Ethiopia</div>
                </td>
                <td style="width: 20%;"></td>
            </tr>
        </table>
        <hr style="border:0; border-top: 2px solid #333; margin-top: 8px;">
    </div>
    <div class="title">
        <h2>Special Memo</h2>
        <div class="small muted">Reference: #{{ $specialMemo->id }}</div>
    </div>

    <div class="footer">
        <div class="line"></div>
        <div>Africa Centres for Disease Control and Prevention (Africa CDC) • African Union Commission • Addis Ababa, Ethiopia</div>
        <div class="small">Generated on {{ now()->format('d M Y H:i') }}</div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 18; // near bottom
            $pdf->page_text($x, $y, $text, $font, $size, array(0.3,0.3,0.3));
        }
    </script>
    <div class="section">
        <table class="no-border">
            <tr>
                <td class="w-25"><strong>Staff</strong></td>
                <td class="w-50">{{ $specialMemo->staff->full_name ?? ($specialMemo->staff->name ?? 'N/A') }}</td>
                <td class="w-25"><strong>Division</strong></td>
                <td>{{ $specialMemo->division->name ?? $specialMemo->division_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Date</strong></td>
                <td>{{ \Illuminate\Support\Carbon::parse($specialMemo->memo_date ?? $specialMemo->created_at)->format('d M Y') }}</td>
                <td><strong>Request Type</strong></td>
                <td>{{ $specialMemo->requestType->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Fund Type</strong></td>
                <td colspan="3">{{ $specialMemo->fund_type_name ?? $specialMemo->fund_type ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3 class="mb-8">Memo Details</h3>
        <table>
            <tr>
                <th class="w-25">Title</th>
                <td colspan="3">{{ $specialMemo->activity_title ?? $specialMemo->title ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Background</th>
                <td colspan="3">{!! $specialMemo->background ?? '' !!}</td>
            </tr>
            <tr>
                <th>Justification</th>
                <td colspan="3">{!! $specialMemo->justification ?? $specialMemo->activity_request_remarks ?? '' !!}</td>
            </tr>
            @if (!empty($locations) && count($locations))
            <tr>
                <th>Locations</th>
                <td colspan="3">
                    @foreach ($locations as $loc)
                        {{ $loop->first ? '' : ', ' }}{{ $loc->name ?? $loc->location ?? ('#'.$loc->id) }}
                    @endforeach
                </td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h3 class="mb-8">Participants</h3>
        <table>
            <thead>
                <tr>
                    <th class="w-35">Name</th>
                    <th class="w-25">Start</th>
                    <th class="w-25">End</th>
                    <th class="w-15 text-right">Days</th>
                </tr>
            </thead>
            <tbody>
                @php $hasRows = false; @endphp
                @foreach (($internalParticipants ?? []) as $p)
                    @php $hasRows = true; @endphp
                    <tr>
                        <td>{{ optional($p['staff'])->full_name ?? optional($p['staff'])->name ?? '—' }}</td>
                        <td>{{ !empty($p['participant_start']) ? \Illuminate\Support\Carbon::parse($p['participant_start'])->format('d M Y') : '—' }}</td>
                        <td>{{ !empty($p['participant_end']) ? \Illuminate\Support\Carbon::parse($p['participant_end'])->format('d M Y') : '—' }}</td>
                        <td class="text-right">{{ $p['participant_days'] ?? '—' }}</td>
                    </tr>
                @endforeach
                @if (!$hasRows)
                    <tr><td colspan="4" class="text-center muted">No participants listed</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3 class="mb-8">Budget</h3>
        <table>
            <thead>
                <tr>
                    <th class="w-35">Budget Code</th>
                    <th>Description</th>
                    <th class="w-15 text-right">Qty</th>
                    <th class="w-20 text-right">Unit Cost</th>
                    <th class="w-20 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $grand = 0; @endphp
                @foreach (($fundCodes ?? []) as $code)
                    @php
                        $items = [];
                        if (is_array($budgetItems ?? null)) {
                            $items = $budgetItems[$code->id] ?? [];
                        }
                    @endphp
                    @if (!empty($items))
                        <tr>
                            <td colspan="5"><strong>{{ $code->code ?? ('Code #'.$code->id) }}</strong></td>
                        </tr>
                        @foreach ($items as $row)
                            @php 
                                $qty = (int)($row['quantity'] ?? ($row->quantity ?? 1));
                                $unit = (float)($row['unit_cost'] ?? ($row->unit_cost ?? 0));
                                $total = $qty * $unit; 
                                $grand += $total;
                            @endphp
                            <tr>
                                <td class="muted small">{{ $code->code ?? '' }}</td>
                                <td>{{ $row['description'] ?? ($row->description ?? '—') }}</td>
                                <td class="text-right">{{ number_format($qty) }}</td>
                                <td class="text-right">{{ number_format($unit, 2) }}</td>
                                <td class="text-right">{{ number_format($total, 2) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                <tr>
                    <td colspan="4" class="text-right"><strong>Grand Total</strong></td>
                    <td class="text-right"><strong>{{ number_format($grand, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3 class="mb-8">Attachments</h3>
        <table>
            <thead>
                <tr>
                    <th>File Name</th>
                    <th class="w-35">Path</th>
                </tr>
            </thead>
            <tbody>
                @php $hasA = false; @endphp
                @foreach (($attachments ?? []) as $f)
                    @php $hasA = true; @endphp
                    <tr>
                        <td>{{ $f['name'] ?? 'attachment' }}</td>
                        <td class="small">{{ $f['path'] ?? '' }}</td>
                    </tr>
                @endforeach
                @if (!$hasA)
                    <tr><td colspan="2" class="text-center muted">No attachments</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="small muted" style="margin-top: 8px; text-align:center;">
        Generated on {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
