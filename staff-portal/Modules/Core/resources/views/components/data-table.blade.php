@props([
    'paginator' => null,
    'from' => 0,
    'to' => 0,
    'total' => 0,
    'compact' => true,
    'tableClass' => 'table table-striped table-bordered align-middle mb-0',
])

@php
    if ($compact) {
        $tableClass .= ' table-sm';
    }
@endphp

<div {{ $attributes->class(['portal-data-table']) }} wire:loading.class="portal-table-loading">
    @if (isset($toolbar))
        <div class="portal-data-table-toolbar mb-3">
            {{ $toolbar }}
        </div>
    @endif

    @if (isset($filters))
        <div class="portal-data-table-filters mb-3">
            {{ $filters }}
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 small text-muted">
        <span wire:loading.remove wire:target="search,filter,perPage,gotoPage,previousPage,nextPage">
            @if ($total > 0)
                Showing {{ number_format($from) }}–{{ number_format($to) }} of {{ number_format($total) }} records
            @else
                No records found
            @endif
        </span>
        <span wire:loading wire:target="search,filter,perPage,gotoPage,previousPage,nextPage" class="text-success">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Updating…
        </span>
        <span class="badge bg-success">Total: {{ number_format($total) }}</span>
    </div>

    <div class="table-responsive portal-data-table-scroll">
        <table class="{{ $tableClass }}">
            @if (isset($head))
                <thead class="table-light">{{ $head }}</thead>
            @endif
            <tbody wire:loading.class="opacity-50" wire:target="search,filter,perPage,gotoPage,previousPage,nextPage">
                {{ $body ?? $slot }}
            </tbody>
        </table>
    </div>

    @if ($paginator && $paginator->hasPages())
        <div class="mt-3 d-flex justify-content-center portal-data-table-pagination">
            {{ $paginator->links() }}
        </div>
    @endif
</div>

@once
    @push('styles')
    <style>
        .portal-data-table-loading { pointer-events: none; }
        .portal-data-table-scroll { margin-left: 4px; margin-right: 4px; }
        .portal-data-table thead th { white-space: nowrap; vertical-align: middle; }
    </style>
    @endpush
@endonce
