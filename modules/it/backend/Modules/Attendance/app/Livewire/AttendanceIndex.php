<?php

namespace Modules\Attendance\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class AttendanceIndex extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(83);
    }

    public function render()
    {
        return view('attendance::livewire.attendance-index');
    }
}
