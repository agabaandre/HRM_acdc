<?php

namespace Modules\AdManager\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class AdManagerIndex extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(77);
    }

    public function render()
    {
        return view('admanager::livewire.admanager-index');
    }
}
