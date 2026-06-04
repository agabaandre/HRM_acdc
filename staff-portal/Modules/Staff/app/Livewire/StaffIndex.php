<?php

namespace Modules\Staff\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Staff\Services\StaffDirectoryService;
use Modules\Staff\Support\StaffAccess;

#[Layout('core::layouts.app')]
class StaffIndex extends Component
{
    use InteractsWithPortalTable;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filter = 'active';

    public ?string $preset = null;

    protected function queryString(): array
    {
        return array_merge([
            'search' => ['except' => '', 'as' => 'q'],
            'filter' => ['except' => 'active'],
        ], $this->queryStringTable());
    }

    public function mount(): void
    {
        if (! StaffAccess::canViewDirectory()) {
            abort(403);
        }
        if ($this->preset !== null && $this->preset !== '') {
            $this->filter = match ($this->preset) {
                'due', '2' => 'due',
                'expired', '3' => 'expired',
                'former', '4' => 'former',
                'renewal', '7' => 'renewal',
                'all' => 'all',
                default => 'active',
            };
        }
    }

    public function updatedSearch(): void
    {
        $this->resetTablePage();
    }

    public function updatedFilter(): void
    {
        $this->resetTablePage();
    }

    public function render(StaffDirectoryService $directory)
    {
        $statusMap = [
            'active' => [1, 2],
            'due' => 2,
            'expired' => 3,
            'former' => 4,
            'renewal' => 7,
            'all' => null,
        ];
        $statusId = $statusMap[$this->filter] ?? null;

        $paginator = $directory->paginate(
            $this->search,
            $statusId,
            $this->getPage(),
            $this->perPage
        );

        $range = $this->tableRange($paginator);

        return view('staff::livewire.staff-index', [
            'staff' => $paginator,
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $range['total'],
            'filterCounts' => $directory->filterCounts($this->search),
            'canManage' => StaffAccess::canManageStaff(),
        ]);
    }
}
