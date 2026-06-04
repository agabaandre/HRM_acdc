<?php

namespace Modules\Core\Livewire;

use App\Support\SsoJwt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Support\PortalNavigation;

#[Layout('core::layouts.app')]
class CbpHome extends Component
{
    /** @var list<array<string, mixed>> */
    public array $modules = [];

    public function mount(): void
    {
        $permissions = session('user.permissions', []);
        $roleId = (int) (session('user.role_id') ?? session('user.role') ?? 0);
        $session = session('user', []);

        if (! \App\Support\LegacySchema::has('cbp_modules')) {
            $this->modules = $this->fallbackModules($session);

            return;
        }

        $rows = DB::table('cbp_modules')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();

        foreach ($rows as $row) {
            if (! $this->userCanSeeModule($row, $permissions, $roleId)) {
                continue;
            }
            $href = $this->resolveModuleHref($row, $session);
            $this->modules[] = [
                'label' => $row->system_name,
                'desc' => $row->description,
                'icon' => $row->icon_class,
                'href' => $href,
                'module_key' => $row->module_key,
            ];
        }
    }

    public function render()
    {
        return view('core::livewire.cbp-home');
    }

    /**
     * @param  list<int|string>  $permissions
     */
    protected function userCanSeeModule(object $row, array $permissions, int $roleId): bool
    {
        if (! PortalNavigation::can($permissions, (string) $row->permission_code)) {
            return false;
        }

        return ! ((int) $row->is_production === 0 && $roleId !== 10);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function resolveModuleHref(object $row, array $session): string
    {
        $base = rtrim((string) config('staff-portal.base_url'), '/');
        $url = (string) $row->base_url;
        if ((int) $row->uses_staff_portal_token === 1) {
            $token = rawurlencode(SsoJwt::encode($session));
            if ($row->target_resolver === 'finance_host') {
                $dev = $row->base_url_development ?: '';
                $host = request()->getHost();
                if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
                    return rtrim($dev, '/').'?token='.$token;
                }

                return 'https://'.$host.'/'.trim((string) ($row->base_url_production ?: $url), '/').'?token='.$token;
            }

            return $base.'/'.trim($url, '/').'?token='.$token;
        }

        if ($url === '' || $url === 'dashboard') {
            return route('dashboard.index');
        }

        return $base.'/'.$url;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return list<array<string, mixed>>
     */
    protected function fallbackModules(array $session): array
    {
        $token = rawurlencode(SsoJwt::encode($session));
        $base = rtrim((string) config('staff-portal.base_url'), '/');

        return [
            ['label' => 'Staff Portal', 'desc' => 'HR and staff records', 'icon' => 'fa-users', 'href' => route('dashboard.index'), 'module_key' => 'staff_portal'],
            ['label' => 'APM', 'desc' => 'Approvals', 'icon' => 'fa-sitemap', 'href' => $base.'/../apm?token='.$token, 'module_key' => 'approvals_management'],
        ];
    }
}
