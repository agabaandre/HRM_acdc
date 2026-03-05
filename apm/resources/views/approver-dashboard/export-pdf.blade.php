{{-- Approver Dashboard PDF export - inline styles for mPDF --}}
<div style="font-family: DejaVu Sans, sans-serif; font-size: 10pt;">
    <h2 style="color: #2c3e50; margin-bottom: 4px;">Approver Dashboard</h2>
    <p style="color: #6c757d; font-size: 9pt; margin: 0 0 12px 0;">Generated on {{ now()->format('d F Y \a\t H:i') }}</p>

    @if(!empty($filters_summary))
    <div style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 8px 12px; margin-bottom: 16px; font-size: 9pt;">
        <strong>Filters:</strong> {{ $filters_summary }}
    </div>
    @endif

    <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 9pt;">
        <thead>
            <tr style="background: #119a48; color: #ffffff;">
                <th style="text-align: center; width: 28px; color: #ffffff;">#</th>
                <th style="text-align: left; color: #ffffff;">Approver</th>
                <th style="text-align: left; color: #ffffff;">Last approval</th>
                <th style="text-align: left; color: #ffffff;">Email</th>
                <th style="text-align: left; color: #ffffff;">Division</th>
                <th style="text-align: left; color: #ffffff;">Roles</th>
                <th style="text-align: center; color: #ffffff;">Pending</th>
                <th style="text-align: center; color: #ffffff;">Total pending</th>
                <th style="text-align: center; color: #ffffff;">Total handled</th>
                <th style="text-align: center; color: #ffffff;">Avg. time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($approvers ?? [] as $index => $row)
            <tr style="{{ $index % 2 === 1 ? 'background: #f8f9fa;' : '' }}">
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $row['approver_name'] ?? '—' }}</td>
                <td>{{ $row['last_approval_date_display'] ?? '—' }}</td>
                <td>{{ $row['approver_email'] ?? '—' }}</td>
                <td>{{ $row['division_name'] ?? 'N/A' }}</td>
                <td>{{ is_array($row['roles'] ?? null) ? implode(', ', $row['roles']) : ($row['role'] ?? '—') }}</td>
                <td style="text-align: center;">{{ $row['pending_items_display'] ?? '—' }}</td>
                <td style="text-align: center;">{{ $row['total_pending'] ?? 0 }}</td>
                <td style="text-align: center;">{{ $row['total_handled'] ?? 0 }}</td>
                <td style="text-align: center;">{{ $row['avg_approval_time_display'] ?? 'No data' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align: center; padding: 16px; color: #6c757d;">No approvers to display.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($summary))
    <div style="margin-top: 16px; font-size: 9pt; color: #6c757d;">
        <strong>Summary:</strong> {{ $summary }}
    </div>
    @endif
</div>
