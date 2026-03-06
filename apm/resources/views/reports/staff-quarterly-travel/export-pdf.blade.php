{{-- Staff Quarterly Travel Report PDF export - inline styles for mPDF --}}
<div style="font-family: DejaVu Sans, sans-serif; font-size: 10pt;">
    <h2 style="color: #2c3e50; margin-bottom: 4px;">Staff Quarterly Travel Days</h2>
    <p style="color: #6c757d; font-size: 9pt; margin: 0 0 12px 0;">Generated on {{ now()->format('d F Y \a\t H:i') }}</p>
    <p style="font-size: 8pt; color: #6c757d; margin: 0 0 12px 0;">This report includes only approved matrices; approved change requests override activity participants; travel days are from internal_participants. Division shown is the staff member&apos;s division.</p>

    @if(!empty($filters_summary))
    <div style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 8px 12px; margin-bottom: 16px; font-size: 9pt;">
        <strong>Filters:</strong> {{ $filters_summary }}
    </div>
    @endif

    <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 9pt;">
        <thead>
            <tr style="background: #119a48; color: #ffffff;">
                <th style="text-align: center; width: 28px; color: #ffffff;">#</th>
                <th style="text-align: left; color: #ffffff;">Staff Name</th>
                <th style="text-align: left; color: #ffffff;">Division</th>
                <th style="text-align: left; color: #ffffff;">Year &amp; Quarter</th>
                <th style="text-align: center; color: #ffffff;">Number of QM Activities</th>
                <th style="text-align: center; color: #ffffff;">Approved Travel Days</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows ?? [] as $index => $row)
            <tr style="{{ $index % 2 === 1 ? 'background: #f8f9fa;' : '' }}">
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $row['staff_name'] ?? '—' }}</td>
                <td>{{ $row['division_name'] ?? '—' }}</td>
                <td>{{ $row['year_quarter'] ?? '—' }}</td>
                <td style="text-align: center;">{{ $row['activity_count'] ?? 0 }}</td>
                <td style="text-align: center;">{{ $row['approved_travel_days'] ?? 0 }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 16px; color: #6c757d;">No data to display.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
