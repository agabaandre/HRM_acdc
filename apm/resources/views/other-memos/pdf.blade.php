<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; }
        h1 { font-size: 16pt; margin-bottom: 6px; }
        .meta { font-size: 9pt; color: #555; margin-bottom: 16px; }
        .field { margin-bottom: 12px; }
        .label { font-weight: bold; display: block; margin-bottom: 4px; }
        .box { border: 1px solid #ccc; padding: 8px; min-height: 24px; }
        table.trail { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9pt; }
        table.trail th, table.trail td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: top; }
        table.trail th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>{{ $memo->memo_type_name_snapshot }}</h1>
    <div class="meta">
        Document: <strong>{{ $memo->document_number }}</strong><br>
        Status: {{ strtoupper($memo->overall_status) }}<br>
        @if ($memo->approved_at)
            Approved: {{ $memo->approved_at->format('Y-m-d H:i') }}<br>
        @endif
        Creator:
        @if ($memo->creator)
            {{ trim(($memo->creator->title ? $memo->creator->title . ' ' : '') . $memo->creator->fname . ' ' . $memo->creator->lname) }}
        @endif
    </div>

    @foreach ($memo->fields_schema_snapshot ?? [] as $field)
        @php
            $k = $field['field'] ?? '';
            $enabled = ! array_key_exists('enabled', $field) || filter_var($field['enabled'], FILTER_VALIDATE_BOOLEAN);
        @endphp
        @if ($k === '' || ! $enabled)
            @continue
        @endif
        @php $val = $memo->payload[$k] ?? ''; @endphp
        <div class="field">
            <span class="label">{{ $field['display'] ?? $k }}</span>
            <div class="box">
                @if (($field['field_type'] ?? '') === 'text_summernote')
                    {!! $val !!}
                @else
                    {{ is_scalar($val) || $val === null ? $val : json_encode($val) }}
                @endif
            </div>
        </div>
    @endforeach

    <h2 style="font-size: 12pt; margin-top: 24px;">Approval trail</h2>
    <table class="trail">
        <thead>
            <tr>
                <th>When</th>
                <th>Action</th>
                <th>By</th>
                <th>Step</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memo->approvalTrails as $t)
                <tr>
                    <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $t->action }}</td>
                    <td>
                        @if ($t->staff)
                            {{ trim(($t->staff->fname ?? '') . ' ' . ($t->staff->lname ?? '')) }}
                        @else
                            #{{ $t->staff_id }}
                        @endif
                    </td>
                    <td>{{ $t->approval_order ?: '—' }}</td>
                    <td>{{ $t->remarks }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
