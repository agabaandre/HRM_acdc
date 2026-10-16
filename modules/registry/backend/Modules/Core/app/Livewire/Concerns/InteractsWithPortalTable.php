<?php

namespace Modules\Core\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * Server-side pagination + auto-refresh filters for portal list pages.
 *
 * Usage in a Livewire component:
 * - use InteractsWithPortalTable;
 * - public string $search = '';  // wire:model.live.debounce via x-core::filter-search
 * - call $this->resetTablePage() in updatedSearch(), updatedFilter(), etc.
 * - pass LengthAwarePaginator to x-core::data-table
 */
trait InteractsWithPortalTable
{
    use WithPagination;

    public int $perPage = 20;

    protected string $paginationTheme = 'bootstrap';

    protected function queryStringTable(): array
    {
        return [
            'perPage' => ['except' => 20, 'as' => 'per_page'],
        ];
    }

    public function updatedPerPage(): void
    {
        $max = (int) config('core.portal-table.max_per_page', 100);
        $this->perPage = min($max, max(10, (int) $this->perPage));
        $this->resetTablePage();
    }

    protected function resetTablePage(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{from: int, to: int, total: int}
     */
    protected function tableRange(LengthAwarePaginator $paginator): array
    {
        $total = $paginator->total();
        if ($total === 0) {
            return ['from' => 0, 'to' => 0, 'total' => 0];
        }

        $from = (($paginator->currentPage() - 1) * $paginator->perPage()) + 1;
        $to = min($from + $paginator->count() - 1, $total);

        return ['from' => $from, 'to' => $to, 'total' => $total];
    }
}
