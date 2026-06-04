<?php

namespace Modules\Staff\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class StaffBirthdays extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(41);
    }

    public function render()
    {
        $month = (int) date('n');
        $staff = DB::table('staff')
            ->whereNotNull('date_of_birth')
            ->whereRaw('MONTH(date_of_birth) = ?', [$month])
            ->orderByRaw('DAY(date_of_birth)')
            ->limit(200)
            ->get();

        return view('staff::livewire.staff-birthdays', compact('staff'));
    }
}
