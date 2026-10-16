<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($expiry['is_expired'] ?? false) ? 'License expired' : 'License expiring soon' }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:24px 28px 8px;">
                <p style="margin:0;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#0d7a3a;font-weight:700;">Africa CDC · IT</p>
                <h1 style="margin:8px 0 0;font-size:22px;">
                    {{ ($expiry['is_expired'] ?? false) ? 'License expired' : 'License expiring soon' }}
                </h1>
                <p style="margin:6px 0 0;color:#64748b;">{{ $license->name }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 24px;font-size:15px;line-height:1.5;">
                <p>Hello <strong>{{ $recipientName }}</strong>,</p>
                <p>
                    @if ($expiry['is_expired'] ?? false)
                        The following software license has <strong>expired</strong> and may need renewal:
                    @else
                        The following software license is <strong>approaching expiry</strong>:
                    @endif
                </p>
                <ul style="margin:0;padding-left:1.25rem;">
                    <li style="margin-bottom:0.35rem;"><strong>Name:</strong> {{ $license->name }}</li>
                    @if ($license->vendor)
                        <li style="margin-bottom:0.35rem;"><strong>Vendor:</strong> {{ $license->vendor }}</li>
                    @endif
                    <li style="margin-bottom:0.35rem;">
                        <strong>Expiry date:</strong> {{ $license->expiry_date?->format('Y-m-d') ?? '—' }}
                    </li>
                    <li style="margin-bottom:0.35rem;">
                        <strong>Days remaining:</strong>
                        @if (($expiry['days_until_expiry'] ?? null) === null)
                            —
                        @elseif (($expiry['days_until_expiry'] ?? 0) < 0)
                            Expired {{ abs((int) $expiry['days_until_expiry']) }} day(s) ago
                        @else
                            {{ (int) $expiry['days_until_expiry'] }} day(s)
                        @endif
                    </li>
                </ul>
                <p>Please review the renewal and update the license record when it is complete.</p>
                <p style="margin-top:1.25rem;">
                    <a href="{{ $licensesUrl }}" style="display:inline-block;background:#0d7a3a;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:700;">Open licenses</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
