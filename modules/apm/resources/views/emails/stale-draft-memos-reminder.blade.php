<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stale draft memos — Africa CDC APM</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 650px; margin: 0 auto; background: #fff; border: 1px solid #ddd; padding: 30px; border-radius: 8px; }
        h1 { font-size: 20px; color: #856404; margin-top: 0; }
        .summary { background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; margin: 20px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; }
        th { background: #f8f9fa; }
        .footer { margin-top: 24px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>Stale draft memos holding budget</h1>
    <p>Hello {{ $staffName ?? 'colleague' }},</p>
    <div class="summary">
        <strong>{{ $staleCount ?? 0 }}</strong> draft memo(s) on your account are older than
        <strong>{{ $draftMaxAgeMonths ?? 2 }} month(s)</strong> and still include budget lines.
        These tie up fund code balances until you submit, archive, or delete them.
    </div>
    <p>Please review the items below. If you no longer need them, delete the drafts to release budget for colleagues.</p>

    @if(!empty($nextWeeklyArchiveRun))
        <p><strong>Auto-archive:</strong> Stale drafts that remain unacted are archived automatically each Monday.
        The next weekly archive run is <strong>{{ $nextWeeklyArchiveRun }}</strong>.</p>
    @endif

    @if(!empty($staleDraftItems))
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Document</th>
                    <th>Last updated</th>
                    <th>Budget</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staleDraftItems as $item)
                    <tr>
                        <td>{{ $item['type_label'] ?? $item['type'] ?? '' }}</td>
                        <td>
                            @if(!empty($item['edit_url']))
                                <a href="{{ $item['edit_url'] }}">{{ $item['title'] ?? 'Untitled' }}</a>
                            @else
                                {{ $item['title'] ?? 'Untitled' }}
                            @endif
                        </td>
                        <td>{{ $item['document_number'] ?? '—' }}</td>
                        <td>{{ $item['updated_at'] ?? '' }}</td>
                        <td>${{ number_format((float) ($item['budget_total'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">Africa CDC APM — automated reminder. Adjust thresholds under System configs → App settings → Budget.</p>
</div>
</body>
</html>
