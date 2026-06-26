@props([
    'name',
    'id' => null,
    'value' => '',
    'required' => false,
    'minHeight' => '200px',
    'toolbar' => 'full',
    'disabled' => false,
    'invalid' => false,
    'class' => '',
    'placeholder' => '',
])

@php
    $sourceId = $id ?? 'apm-rich-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name);
    $editorId = $sourceId . '-editor';
@endphp

<div @class([
    'apm-quill-wrap',
    $class,
    'is-invalid' => $invalid,
])
    data-apm-quill-toolbar="{{ $toolbar }}"
    data-apm-quill-min-height="{{ $minHeight }}"
    @if ($disabled) data-apm-quill-disabled @endif
>
    <div id="{{ $editorId }}" class="apm-quill-editor border rounded bg-white" style="min-height: {{ $minHeight }}"></div>
    <textarea
        class="d-none apm-quill-source"
        name="{{ $name }}"
        id="{{ $sourceId }}"
        @if ($required) required @endif
        @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
    >{{ $value }}</textarea>
</div>
