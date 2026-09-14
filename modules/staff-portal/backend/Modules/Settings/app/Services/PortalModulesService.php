<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Settings\Models\PortalModuleSetting;

class PortalModulesService
{
    public const CACHE_KEY = 'portal_modules.enabled_map_v1';

    /**
     * Catalog of toggleable SPA modules.
     *
     * @return array<string, array{label: string, description: string, default: bool}>
     */
    public static function catalog(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Executive / operational dashboard.',
                'default' => true,
            ],
            'staff' => [
                'label' => 'Staff',
                'description' => 'Staff directory, profiles, contracts, and related tools.',
                'default' => true,
            ],
            'leave' => [
                'label' => 'Leave',
                'description' => 'Leave balances, requests, plans, and approvals.',
                'default' => true,
            ],
            'payroll' => [
                'label' => 'Payroll',
                'description' => 'Wage runs, payslips, loans, and payroll setup.',
                'default' => false,
            ],
            'performance' => [
                'label' => 'Performance',
                'description' => 'PPA / mid-term / end-term performance workflows.',
                'default' => true,
            ],
            'tasks' => [
                'label' => 'Tasks',
                'description' => 'Task lists and weekly tasks.',
                'default' => true,
            ],
            'workplan' => [
                'label' => 'Workplan',
                'description' => 'Workplan activities and tracking.',
                'default' => true,
            ],
            'admanager' => [
                'label' => 'AD Manager',
                'description' => 'Active Directory account management tools.',
                'default' => true,
            ],
            'settings' => [
                'label' => 'Settings',
                'description' => 'Portal configuration hub (always kept available for admins).',
                'default' => true,
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function enabledMap(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function (): array {
            $rows = PortalModuleSetting::query()->pluck('is_enabled', 'module_key')->all();
            $map = [];
            foreach (self::catalog() as $key => $meta) {
                if (array_key_exists($key, $rows)) {
                    $map[$key] = (bool) $rows[$key];
                } else {
                    $map[$key] = (bool) $meta['default'];
                }
            }

            // Settings must remain reachable so modules can be re-enabled.
            $map['settings'] = true;

            return $map;
        });
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        $keys = [];
        foreach ($this->enabledMap() as $key => $on) {
            if ($on) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function isEnabled(string $moduleKey): bool
    {
        $map = $this->enabledMap();

        return $map[$moduleKey] ?? (bool) (self::catalog()[$moduleKey]['default'] ?? true);
    }

    /**
     * @return list<array{key: string, label: string, description: string, enabled: bool, locked?: bool}>
     */
    public function adminList(): array
    {
        $map = $this->enabledMap();
        $out = [];
        foreach (self::catalog() as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'enabled' => (bool) ($map[$key] ?? $meta['default']),
                'locked' => $key === 'settings',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, bool>  $modules
     * @return list<array{key: string, label: string, description: string, enabled: bool, locked?: bool}>
     */
    public function save(array $modules): array
    {
        foreach (self::catalog() as $key => $meta) {
            if ($key === 'settings') {
                PortalModuleSetting::query()->updateOrCreate(
                    ['module_key' => $key],
                    ['is_enabled' => true],
                );

                continue;
            }

            if (! array_key_exists($key, $modules)) {
                continue;
            }

            PortalModuleSetting::query()->updateOrCreate(
                ['module_key' => $key],
                ['is_enabled' => (bool) $modules[$key]],
            );
        }

        Cache::forget(self::CACHE_KEY);

        return $this->adminList();
    }
}
