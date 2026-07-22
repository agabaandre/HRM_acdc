<div style="font-family: Arial, DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b;">
    <h2 style="color: #911C39; margin: 0 0 4px 0; font-size: 16pt;">Agent monthly report</h2>
    <p style="color: #64748b; font-size: 9pt; margin: 0 0 12px 0;">
        {{ $period_label }} — {{ $user_name }} · Generated {{ now()->format('d F Y \a\t H:i') }}
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 16px; border-color: #cbd5e1;">
        <tr style="background: #f8fafc;">
            <td><strong>Tickets worked</strong></td>
            <td>{{ $tickets_worked ?? 0 }}</td>
            <td><strong>Tickets resolved</strong></td>
            <td>{{ $tickets_resolved ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Avg first response (min)</strong></td>
            <td>{{ $avg_first_response_minutes ?? 'n/a' }}</td>
            <td><strong>Model</strong></td>
            <td>{{ $ai_model ?? '—' }}</td>
        </tr>
    </table>

    <h3 style="color: #911C39; font-size: 12pt; margin: 0 0 8px 0;">AI summary</h3>
    <div style="border: 1px solid #e2e8f0; padding: 10px 12px; white-space: pre-wrap; font-size: 9pt; line-height: 1.45;">
        {{ $ai_summary ?: 'No summary available.' }}
    </div>
</div>
