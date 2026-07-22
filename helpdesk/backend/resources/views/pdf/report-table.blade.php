{{-- Generic tabular PDF body (Africa CDC header/footer applied by HelpdeskPdfReportService) --}}
<div style="font-family: Arial, DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b;">
    <h2 style="color: #911C39; margin: 0 0 4px 0; font-size: 16pt;">{{ $title }}</h2>
    <p style="color: #64748b; font-size: 9pt; margin: 0 0 12px 0;">Generated {{ now()->format('d F Y \a\t H:i') }}</p>

    @if(!empty($summary_lines) && is_array($summary_lines))
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; margin-bottom: 14px; font-size: 9pt;">
            @foreach($summary_lines as $line)
                <div style="margin: 2px 0;">{{ $line }}</div>
            @endforeach
        </div>
    @endif

    @if(!empty($headings) && !empty($rows))
        <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 8pt; border-color: #cbd5e1;">
            <thead>
                <tr style="background: #911C39; color: #ffffff;">
                    <th style="text-align: center; width: 28px; color: #ffffff;">#</th>
                    @foreach($headings as $heading)
                        <th style="text-align: left; color: #ffffff;">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $index => $row)
                    <tr style="{{ $index % 2 === 1 ? 'background: #f8fafc;' : '' }}">
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        @foreach($row as $cell)
                            <td>{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="margin-top: 10px; font-size: 8pt; color: #64748b;">{{ count($rows) }} row(s) shown (max export limit may apply).</p>
    @else
        <p style="color: #64748b;">No rows to display.</p>
    @endif
</div>
