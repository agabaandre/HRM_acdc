@props([
    'model' => 'search',
    'label' => 'Search',
    'placeholder' => 'Type to search…',
    'debounce' => null,
    'col' => 'col-md-4',
])

@php
    $debounceMs = (int) ($debounce ?? config('core.portal-table.search_debounce_ms', 350));
@endphp

<div class="{{ $col }}">
    <label class="form-label small mb-1">{{ $label }}</label>
    <input
        type="search"
        class="form-control form-control-sm"
        placeholder="{{ $placeholder }}"
        wire:model.live.debounce.{{ $debounceMs }}ms="{{ $model }}"
        autocomplete="off"
        {{ $attributes }}
    >
</div>
