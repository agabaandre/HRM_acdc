@props([
    'model' => 'perPage',
    'label' => 'Per page',
    'col' => 'col-md-2',
])

<div class="{{ $col }}">
    <label class="form-label small mb-1">{{ $label }}</label>
    <select class="form-select form-select-sm" wire:model.live="{{ $model }}">
        <option value="10">10</option>
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="75">75</option>
        <option value="100">100</option>
    </select>
</div>
