@php
    $schema = \App\Models\MemoTypeDefinition::normalizeFieldsSchemaRows($schema ?? []);
    $values = is_array($values ?? null) ? $values : [];
@endphp
@foreach ($schema as $field)
    @php
        $k = $field['field'] ?? '';
        if ($k === '') {
            continue;
        }
        $enabled = ! array_key_exists('enabled', $field) || filter_var($field['enabled'], FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            continue;
        }
        $val = $values[$k] ?? '';
        $label = $field['display'] ?? $k;
        $type = $field['field_type'] ?? 'text';
        $isEmpty = $val === null || $val === '';
        if ($type === 'text_summernote' && is_string($val)) {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($val)));
            $isEmpty = $plain === '' || $plain === "\xc2\xa0";
        }
    @endphp
    <div class="card content-section border-0 mb-4">
        <div class="card-header bg-transparent border-0 py-3">
            <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                <i class="bx bx-detail"></i>{{ $label }}
            </h6>
        </div>
        <div class="card-body pt-0">
            @if ($isEmpty)
                <span class="text-muted field-value-null">—</span>
            @elseif ($type === 'text_summernote')
                {!! \App\Helpers\PrintHelper::sanitizeRichTextForMpdf((string) $val) !!}
            @elseif ($type === 'textarea')
                <div class="text-pre-wrap">{{ $val }}</div>
            @else
                <div class="field-value">{{ is_scalar($val) ? $val : json_encode($val) }}</div>
            @endif
        </div>
    </div>
@endforeach
