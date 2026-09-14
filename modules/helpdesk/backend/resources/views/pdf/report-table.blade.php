{{-- Generic tabular PDF body (Africa CDC header/footer applied by HelpdeskPdfReportService) --}}
<div style="font-family: Arial, DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b;">
    <h2 style="color: #911C39; margin: 0 0 10px 0; font-size: 16pt;">{{ $title }}</h2>

    @if(!empty($headings) && !empty($rows))
        <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 8pt; border-color: #cbd5e1; margin-bottom: 5px;">
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
    @else
        <p style="color: #64748b;">No rows to display.</p>
    @endif
</div>
