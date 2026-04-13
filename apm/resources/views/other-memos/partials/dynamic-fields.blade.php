@php
    $schema = $schema ?? [];
    $values = $values ?? [];
    $readonly = !empty($readonly);
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
        $req = !empty($field['required']);
        $type = $field['field_type'] ?? 'text';
    @endphp
    <div class="mb-3">
        <label class="form-label fw-semibold">{{ $label }} @if ($req && !$readonly)<span class="text-danger">*</span>@endif</label>
        @if ($readonly)
            <div class="border rounded p-2 bg-light">
                @if ($type === 'text_summernote')
                    {!! $val !!}
                @elseif($type === 'textarea')
                    <div class="text-pre-wrap">{{ $val }}</div>
                @else
                    {{ is_scalar($val) || $val === null ? $val : json_encode($val) }}
                @endif
            </div>
        @else
            @if ($type === 'text_summernote')
                <textarea name="payload[{{ $k }}]" id="payload_{{ $k }}" class="form-control summernote" rows="4" @if ($req) required @endif>{{ old('payload.' . $k, $val) }}</textarea>
            @elseif($type === 'textarea')
                <textarea name="payload[{{ $k }}]" class="form-control" rows="3" @if ($req) required @endif>{{ old('payload.' . $k, $val) }}</textarea>
            @elseif($type === 'number')
                <input type="number" step="any" name="payload[{{ $k }}]" class="form-control" value="{{ old('payload.' . $k, $val) }}" @if ($req) required @endif>
            @elseif($type === 'date')
                <input type="date" name="payload[{{ $k }}]" class="form-control" value="{{ old('payload.' . $k, $val) }}" @if ($req) required @endif>
            @elseif($type === 'email')
                <input type="email" name="payload[{{ $k }}]" class="form-control" value="{{ old('payload.' . $k, $val) }}" @if ($req) required @endif>
            @else
                <input type="text" name="payload[{{ $k }}]" class="form-control" value="{{ old('payload.' . $k, $val) }}" @if ($req) required @endif>
            @endif
        @endif
    </div>
@endforeach
