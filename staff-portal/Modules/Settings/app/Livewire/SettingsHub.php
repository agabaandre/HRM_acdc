<?php

namespace Modules\Settings\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;

#[Layout('core::layouts.app')]
class SettingsHub extends Component
{
    use ChecksPortalPermission;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizePortal(15);
    }

    public function render()
    {
        $cards = [
            ['route' => 'settings.lookup', 'params' => ['table' => 'cbp_modules'], 'label' => 'CBP modules', 'icon' => 'bx-grid-alt', 'special' => true],
            ['route' => 'settings.lookup', 'params' => ['table' => 'nationalities'], 'label' => 'Nationalities', 'icon' => 'bx-globe'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'duty_stations'], 'label' => 'Duty Stations', 'icon' => 'bx-map'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'contracting_institutions'], 'label' => 'Contracting Institutions', 'icon' => 'bx-network-chart'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'contract_types'], 'label' => 'Contract Types', 'icon' => 'bx-group'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'directorates'], 'label' => 'Directorates', 'icon' => 'bx-git-branch'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'divisions'], 'label' => 'Divisions', 'icon' => 'bx-sitemap', 'special' => true],
            ['route' => 'settings.lookup', 'params' => ['table' => 'kin_relationship_types'], 'label' => 'Next of kin relationships', 'icon' => 'bx-group'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'grades'], 'label' => 'Grades', 'icon' => 'bx-bar-chart-alt-2'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'jobs'], 'label' => 'Jobs', 'icon' => 'bx-briefcase'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'funders'], 'label' => 'Funders', 'icon' => 'bx-dollar'],
            ['route' => 'settings.leave', 'label' => 'Leave policy & types', 'icon' => 'bx-time-five'],
            ['route' => 'settings.performance', 'label' => 'Performance & workflows', 'icon' => 'bx-line-chart'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'regions'], 'label' => 'Regions', 'icon' => 'bx-compass'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'units'], 'label' => 'Units', 'icon' => 'bx-building'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'training_skills'], 'label' => 'Training Skills', 'icon' => 'bx-book'],
            ['route' => 'settings.lookup', 'params' => ['table' => 'au_values'], 'label' => 'AU Values', 'icon' => 'bx-star'],
        ];

        if ($this->search !== '') {
            $term = strtolower($this->search);
            $cards = array_values(array_filter($cards, fn ($c) => str_contains(strtolower($c['label']), $term)));
        }

        return view('settings::livewire.settings-hub', ['cards' => $cards]);
    }
}
