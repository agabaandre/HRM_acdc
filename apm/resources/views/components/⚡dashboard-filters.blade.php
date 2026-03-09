<?php

use Livewire\Component;

new class extends Component
{
    // Placeholder for dashboard filters; can be wired to filter state and dispatch events for DataTables.
    public $year = '';
    public $divisionId = '';

    public function mount()
    {
        $this->year = (string) now()->year;
    }

    public function applyFilters()
    {
        $this->dispatch('filters-applied', year: $this->year, divisionId: $this->divisionId);
    }
};
?>

<div class="filter-card card mb-3" wire:ignore.self>
    <div class="card-header">
        <h6 class="mb-0">Quick filters (Livewire)</h6>
    </div>
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small">Year</label>
                <select class="form-select form-select-sm" wire:model.live="year" style="width: 100px;">
                    @foreach (range(now()->year, now()->year - 5) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-success" wire:click="applyFilters">Apply</button>
            </div>
        </div>
    </div>
</div>
