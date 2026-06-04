<?php

namespace Modules\Tasks\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class WeeklyTasks extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(75);
    }

    public function render()
    {
        $divisionId = (int) (session('user.division_id') ?? 0);
        $staffList = $divisionId > 0
            ? DB::table('staff')->where('division_id', $divisionId)->orderBy('lname')->limit(50)->get()
            : collect();
        $divisions = DB::table('divisions')->orderBy('division_name')->get();

        return view('tasks::livewire.weekly-tasks', compact('staffList', 'divisions'));
    }
}
