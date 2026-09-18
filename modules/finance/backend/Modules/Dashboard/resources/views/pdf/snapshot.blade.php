<div style="font-family: arial, sans-serif; font-size: 11pt; color: #2c3e50;">
    <h2 style="color:#0d7a3a;margin:0 0 8px;">Staff Dashboard Snapshot</h2>
    <p style="margin:0 0 16px;color:#6c757d;font-size:9pt;">Generated {{ $generatedAt }}</p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;">
        <tr>
            <td style="border:1px solid #dee2e6;background:#e8f5ee;"><strong>Active staff</strong><br>{{ $data['staff'] }}</td>
            <td style="border:1px solid #dee2e6;background:#fff8e6;"><strong>Due in 2 months</strong><br>{{ $data['two_months'] }}</td>
            <td style="border:1px solid #dee2e6;background:#eef5f9;"><strong>Under renewal</strong><br>{{ $data['staff_renewal'] }}</td>
            <td style="border:1px solid #dee2e6;background:#fdecea;"><strong>Expired</strong><br>{{ $data['expired'] }}</td>
        </tr>
    </table>

    <h3 style="color:#119a48;font-size:12pt;">Staff by division</h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px;">
        <thead>
            <tr style="background:#119a48;color:#fff;">
                <th align="left">Division</th>
                <th align="right">Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['staff_by_division']['division'] ?? []) as $i => $label)
                <tr>
                    <td style="border-bottom:1px solid #e9ecef;">{{ $label }}</td>
                    <td align="right" style="border-bottom:1px solid #e9ecef;">{{ $data['staff_by_division']['value'][$i] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="color:#119a48;font-size:12pt;">Staff by contract type</h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <thead>
            <tr style="background:#2c3e50;color:#fff;">
                <th align="left">Contract type</th>
                <th align="right">Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['staff_by_contract']['contract_type'] ?? []) as $i => $label)
                <tr>
                    <td style="border-bottom:1px solid #e9ecef;">{{ $label }}</td>
                    <td align="right" style="border-bottom:1px solid #e9ecef;">{{ $data['staff_by_contract']['value'][$i] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="color:#119a48;font-size:12pt;">Staff by duty station country</h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px;">
        <thead>
            <tr style="background:#119a48;color:#fff;">
                <th align="left">Country</th>
                <th align="right">Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['staff_by_duty_station_map']['points'] ?? []) as $point)
                <tr>
                    <td style="border-bottom:1px solid #e9ecef;">{{ $point['name'] ?? $point['iso2'] ?? '' }}</td>
                    <td align="right" style="border-bottom:1px solid #e9ecef;">{{ $point['value'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="color:#119a48;font-size:12pt;">Staff by nationality</h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <thead>
            <tr style="background:#2c3e50;color:#fff;">
                <th align="left">Nationality</th>
                <th align="right">Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['staff_by_nationality_map']['points'] ?? []) as $point)
                <tr>
                    <td style="border-bottom:1px solid #e9ecef;">{{ $point['name'] ?? $point['iso2'] ?? '' }}</td>
                    <td align="right" style="border-bottom:1px solid #e9ecef;">{{ $point['value'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No data</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
