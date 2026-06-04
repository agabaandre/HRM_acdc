<?php

namespace Modules\Tasks\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class TasksHub extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(78);
    }

    public function render()
    {
        return view('tasks::livewire.tasks-hub');
    }
}
