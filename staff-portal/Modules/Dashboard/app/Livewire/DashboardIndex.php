<?php

namespace Modules\Dashboard\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Dashboard\Services\DashboardService;
use Illuminate\Support\Facades\DB;

#[Layout('core::layouts.app')]
class DashboardIndex extends Component
{
    use ChecksPortalPermission;

    public ?int $division_id = null;

    public ?int $duty_station_id = null;

    public ?int $funder_id = null;

    public ?int $job_id = null;

    /** @var array<string, mixed> */
    public array $stats = [];

    public function mount(): void
    {
        $this->authorizePortal(76);
        $this->loadStats();
    }

    public function updatedDivisionId(): void
    {
        $this->loadStats();
    }

    public function updatedDutyStationId(): void
    {
        $this->loadStats();
    }

    public function updatedFunderId(): void
    {
        $this->loadStats();
    }

    public function updatedJobId(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->stats = app(DashboardService::class)->getDashboardData(
            $this->division_id,
            $this->duty_station_id,
            $this->funder_id,
            $this->job_id
        );
    }

    public function render()
    {
        return view('dashboard::livewire.dashboard-index', [
            'divisions' => DB::table('divisions')->orderBy('division_name')->get(),
            'dutyStations' => DB::table('duty_stations')->orderBy('duty_station_name')->get(),
            'funders' => DB::table('funders')->orderBy('funder')->get(),
            'jobs' => DB::table('jobs')->orderBy('job_name')->get(),
        ]);
    }
}
