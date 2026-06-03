<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #0f172a; }
        body {
            font-size: 14px;
            font-family: "freesans", arial, sans-serif;
            background: #f6f8fb;
            margin: 40px;
            line-height: 1.8;
            font-style: normal;
        }
        .document-heading {
            text-align: center;
            margin-top: -20px;
            margin-bottom: 15px;
        }
        .document-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 0 0 4px 0;
            color: #100f0f;
            letter-spacing: 0.5px;
            font-style: normal;
        }
        .document-memo-type {
            text-align: center;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: none; }
        .section-label {
            color: #006633;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
            margin-bottom: 10px;
            font-style: normal;
        }
        .subject-text {
            text-decoration: underline;
            font-weight: bold;
            font-style: normal;
            color: #100f0f;
        }
        .memo-body-section {
            margin-top: 15px;
        }
        .memo-body-block {
            text-align: left;
            font-style: normal;
            margin: 0 0 10px 0;
        }
        .memo-body-block:last-child {
            margin-bottom: 0;
        }
        .approver-name {
            font-size: 14px;
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 2px;
            font-style: normal;
        }
        .approver-title {
            color: #666;
            font-size: 12px;
            line-height: 1.1;
            margin-top: 1px;
            font-style: normal;
        }
        .signature-image {
            height: 30px;
            max-width: 80px;
            object-fit: contain;
            filter: contrast(1.2);
            display: block;
            margin: 0;
            padding: 0;
        }
        .signature-date {
            color: #666;
            font-size: 8px;
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }
        .signature-hash {
            color: #999;
            font-size: 8px;
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }
        .text-right { text-align: right; }
        .mb-15 { margin-bottom: 15px; }
        .justify-text {
            text-align: left;
            line-height: 1.6;
            font-style: normal;
        }
        .rich-text-content { margin: 8px 0; text-align: left; font-style: normal; }
        .rich-text-content img, .html-content img {
            max-width: 100% !important;
            height: auto !important;
            display: block;
            margin: 8px 0;
            float: none !important;
        }
        .rich-text-content p { margin: 0 0 8px 0; padding: 0; font-style: normal; }
        {!! \App\Helpers\PrintHelper::memoPdfLayoutStyles() !!}
    </style>
</head>
<body>
@php
    use App\Helpers\PrintHelper;

    /** @var \App\Models\OtherMemo $memo */
    $subjectFieldKey = PrintHelper::otherMemoSubjectFieldKey($memo);
    $subjectText = PrintHelper::otherMemoSubjectText($memo);
    $organizedApprovers = PrintHelper::organizeOtherMemoApproversBySection($memo);
    $approvalTrails = $memo->approvalTrails ?? collect();
    $sectionOrder = ['to', 'through', 'from'];
    $sectionLabels = [
        'to' => 'To:',
        'through' => 'Through:',
        'from' => 'From:',
    ];
    $totalRows = 0;
    foreach ($sectionOrder as $section) {
        $totalRows += count($organizedApprovers[$section] ?? []);
    }
    $dateFileRowspan = max(1, $totalRows);
    $headerDateRendered = false;
    $memoDate = $memo->submitted_at ?? $memo->created_at;
    $schema = \App\Models\MemoTypeDefinition::normalizeFieldsSchemaRows($memo->fields_schema_snapshot ?? []);
    $payload = is_array($memo->payload) ? $memo->payload : [];
@endphp

<div class="document-heading">
    <h1 class="document-title">{{ PrintHelper::otherMemoPdfHeading() }}</h1>
    @if (trim((string) ($memo->memo_type_name_snapshot ?? '')) !== '')
        <div class="document-memo-type approver-title">{{ $memo->memo_type_name_snapshot }}</div>
    @endif
</div>

<table class="mb-15">
    @foreach ($sectionOrder as $section)
        @php $sectionApprovers = $organizedApprovers[$section] ?? []; @endphp
        @if (count($sectionApprovers) === 0)
            @continue
        @endif
        @foreach ($sectionApprovers as $approver)
            <tr>
                <td style="width: 12%; vertical-align: top;">
                    <strong class="section-label">{{ $sectionLabels[$section] ?? strtoupper($section).':' }}</strong>
                </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    @php PrintHelper::renderOtherMemoApproverInfo($approver, $memo, $section); @endphp
                </td>
                <td style="width: 30%; vertical-align: top; text-align: left;">
                    @php PrintHelper::renderOtherMemoSignature($approver, $approvalTrails, $memo); @endphp
                </td>
                @if (! $headerDateRendered)
                    <td style="width: 28%; vertical-align: top;" rowspan="{{ $dateFileRowspan }}">
                        <div class="text-right">
                            <div style="margin-bottom: 20px;">
                                <strong class="section-label">Date:</strong>
                                <span style="font-weight: bold; font-style: normal;">
                                    {{ $memoDate ? $memoDate->format('j F Y') : now()->format('j F Y') }}
                                </span>
                            </div>
                            <div>
                                <br><br>
                                <strong class="section-label">File No:</strong><br>
                                <span style="word-break: break-all; font-weight: bold; font-style: normal;">{{ $memo->document_number ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </td>
                    @php $headerDateRendered = true; @endphp
                @endif
            </tr>
        @endforeach
    @endforeach
</table>

@php PrintHelper::renderMemoPdfPlainField('Subject:', $subjectText, 'subject-text'); @endphp

<div class="memo-body-section">
@foreach ($schema as $field)
    @php
        $k = $field['field'] ?? '';
        $enabled = ! array_key_exists('enabled', $field) || filter_var($field['enabled'], FILTER_VALIDATE_BOOLEAN);
    @endphp
    @if ($k === '' || ! $enabled || ($subjectFieldKey !== null && $k === $subjectFieldKey))
        @continue
    @endif
    @php
        $val = $payload[$k] ?? '';
        $type = $field['field_type'] ?? 'text';
        $isEmpty = $val === null || $val === '';
        if ($type === 'text_summernote' && is_string($val)) {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($val)));
            $isEmpty = $plain === '' || $plain === "\xc2\xa0";
        }
    @endphp
    @if ($isEmpty)
        @continue
    @endif
    @php PrintHelper::renderOtherMemoPdfBodyField($type, $val); @endphp
@endforeach
</div>

</body>
</html>
