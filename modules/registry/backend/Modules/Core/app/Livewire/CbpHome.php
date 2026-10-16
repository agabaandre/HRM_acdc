<?php

namespace Modules\Core\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Support\CbpModulesNav;

#[Layout('core::layouts.app')]
class CbpHome extends Component
{
    /** @var list<array<string, mixed>> */
    public array $modules = [];

    public function mount(): void
    {
        $session = session('user', []);
        $payload = CbpModulesNav::payload(is_array($session) ? $session : []);
        foreach ($payload['modules'] as $mod) {
            $this->modules[] = [
                'label' => $mod['label'] ?? '',
                'desc' => $mod['description'] ?? '',
                'icon' => $mod['icon'] ?? 'fa-th',
                'href' => $mod['href'] ?? '#',
                'module_key' => $mod['module_key'] ?? null,
                'sso_launch' => ! empty($mod['sso_launch']),
            ];
        }
    }

    public function render()
    {
        return view('core::livewire.cbp-home');
    }
}
