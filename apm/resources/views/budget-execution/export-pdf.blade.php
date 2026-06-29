<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 4px; color: #119a48; }
        .meta { font-size: 8px; color: #666; margin-bottom: 10px; }
        .division-block { margin-bottom: 14px; page-break-inside: avoid; }
        .division-title {
            background: #e8f5e9;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: bold;
            border-left: 4px solid #119a48;
            margin-bottom: 4px;
        }
        .division-summary { font-size: 8px; margin-bottom: 6px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; vertical-align: top; }
        th { background: #f5f5f5; font-weight: bold; }
        .title-cell { max-width: 180px; word-wrap: break-word; }
        .text-end { text-align: right; }
        .fc-sub { font-size: 7px; color: #555; }
        .badge-ok { color: #15803d; font-weight: bold; }
        .badge-partial { color: #b45309; font-weight: bold; }
        .badge-none { color: #888; }
    </style>
</head>
<body>
    <h1>APM Budget execution</h1>
    <div class="meta">
        Period: {{ $period_label }}
        @if(!empty($division_filter))
            &nbsp;|&nbsp; Division: {{ $division_filter }}
        @endif
        &nbsp;|&nbsp; Generated: {{ $generated_at }}
    </div>

    @php
        $summary = $payload['summary'] ?? [];
    @endphp
    <div class="meta">
        Initiatives: {{ $summary['initiative_count'] ?? 0 }}
        &nbsp;|&nbsp; With SR/ARF: {{ $summary['with_sr_or_arf'] ?? 0 }}
        &nbsp;|&nbsp; 100% executed: {{ $summary['fully_executed_count'] ?? 0 }}
        &nbsp;|&nbsp; Overall: {{ number_format($summary['execution_pct'] ?? 0, 1) }}%
        ({{ number_format($summary['executed_budget'] ?? 0, 2) }} / {{ number_format($summary['planned_budget'] ?? 0, 2) }})
    </div>

    @foreach($payload['divisions'] ?? [] as $division)
        <div class="division-block">
            <div class="division-title">{{ $division['division_name'] ?? 'Unknown' }}</div>
            <div class="division-summary">
                {{ $division['initiative_count'] ?? 0 }} initiatives
                &nbsp;|&nbsp; Execution {{ number_format($division['execution_pct'] ?? 0, 1) }}%
                &nbsp;|&nbsp; {{ number_format($division['executed_budget'] ?? 0, 2) }} / {{ number_format($division['planned_budget'] ?? 0, 2) }}
            </div>

            @if(!empty($division['fund_codes']))
                <table>
                    <thead>
                        <tr>
                            <th>Fund code</th>
                            <th class="text-end">Planned</th>
                            <th class="text-end">Executed</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end">Working balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($division['fund_codes'] as $fc)
                            <tr>
                                <td>{{ $fc['code'] ?? '' }}<div class="fc-sub">{{ $fc['activity'] ?? '' }}</div></td>
                                <td class="text-end">{{ number_format($fc['planned'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($fc['executed'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($fc['remaining'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($fc['working_balance'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Document</th>
                        <th class="title-cell">Title</th>
                        <th class="text-end">Budget</th>
                        <th class="text-end">Executed</th>
                        <th class="text-end">%</th>
                        <th>Status</th>
                        <th>Fund codes (planned / executed / remaining)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($division['initiatives'] ?? [] as $row)
                        @php
                            $statusClass = ($row['fully_executed'] ?? false) ? 'badge-ok' : (($row['has_sr_or_arf'] ?? false) ? 'badge-partial' : 'badge-none');
                            $statusText = ($row['fully_executed'] ?? false) ? '100%' : (($row['has_sr_or_arf'] ?? false) ? 'Partial' : 'Not started');
                            $fcLines = collect($row['fund_codes'] ?? [])->map(fn ($fc) =>
                                ($fc['code'] ?? '') . ': ' . number_format($fc['planned'] ?? 0, 0) . ' / ' . number_format($fc['executed'] ?? 0, 0) . ' / ' . number_format($fc['remaining'] ?? 0, 0)
                            )->implode('; ');
                        @endphp
                        <tr>
                            <td>{{ match($row['source_type'] ?? '') {
                                'matrix_activity' => 'Matrix',
                                'single_memo' => 'Single',
                                'special_memo' => 'Special',
                                'non_travel_memo' => 'Non-travel',
                                default => $row['source_type'] ?? '',
                            } }}</td>
                            <td>{{ $row['document_number'] ?? '—' }}</td>
                            <td class="title-cell">{{ $row['title'] ?? '—' }}</td>
                            <td class="text-end">{{ number_format($row['planned_budget'] ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($row['executed_budget'] ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($row['execution_pct'] ?? 0, 1) }}%</td>
                            <td class="{{ $statusClass }}">{{ $statusText }}</td>
                            <td class="fc-sub">{{ $fcLines ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
