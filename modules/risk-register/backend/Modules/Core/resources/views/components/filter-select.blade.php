@props([
    'model',
    'label',
    'col' => 'col-md-4',
])

<div class="{{ $col }}">
    <label class="form-label small mb-1">{{ $label }}</label>
    <select class="form-select form-select-sm" wire:model.live="{{ $model }}" {{ $attributes }}>
        {{ $slot }}
    </select>
</div>
