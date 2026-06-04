<?php

namespace Modules\Workplan\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class WorkplanIndex extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(79);
    }

    public function render()
    {
        $plans = DB::table('workplans')->orderByDesc('workplan_id')->limit(50)->get();

        return view('workplan::livewire.workplan-index', compact('plans'));
    }
}
