<?php

namespace Modules\Reports\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('core::layouts.app')]
class ReportsIndex extends Component
{
    public function render()
    {
        return view('reports::livewire.reports-index');
    }
}
