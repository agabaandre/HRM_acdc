<?php

namespace Modules\Staff\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class StaffDataQuality extends Component
{
    use ChecksPortalPermission;

    public function mount(): void
    {
        $this->authorizePortal(71);
    }

    public function render()
    {
        $issues = DB::table('staff')
            ->where(function ($q): void {
                $q->whereNull('work_email')
                    ->orWhere('work_email', '')
                    ->orWhereNull('date_of_birth')
                    ->orWhereNull('SAPNO');
            })
            ->orderBy('lname')
            ->limit(100)
            ->get(['staff_id', 'fname', 'lname', 'work_email', 'SAPNO', 'date_of_birth']);

        return view('staff::livewire.staff-data-quality', compact('issues'));
    }
}
