<?php

namespace Modules\AdManager\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdManager\Services\AdManagerService;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;

#[Layout('core::layouts.app')]
class DisabledAccountsReport extends Component
{
    use ChecksPortalPermission;
    use InteractsWithPortalTable;

    #[Url(as: 'q')]
    public string $search = '';

    protected function queryString(): array
    {
        return array_merge([
            'search' => ['except' => '', 'as' => 'q'],
        ], $this->queryStringTable());
    }

    public function mount(): void
    {
        $this->authorizePortal(77);
    }

    public function updatedSearch(): void
    {
        $this->resetTablePage();
    }

    public function render(AdManagerService $service)
    {
        $paginator = $service->paginateDisabledAccounts($this->search, $this->perPage, $this->getPage());
        $range = $this->tableRange($paginator);

        return view('admanager::livewire.disabled-accounts-report', [
            'staff' => $paginator,
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => $range['total'],
        ]);
    }
}
