<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemConfigsController extends Controller
{
    public const TABS = [
        'jobs' => [
            'label' => 'Jobs',
            'icon' => 'bx-task',
            'description' => 'Maintenance commands, data sync, reminders & document counters',
        ],
        'monitor' => [
            'label' => 'System monitor',
            'icon' => 'bx-server',
            'description' => 'Queue workers, scheduler services & failed jobs',
        ],
        'app-settings' => [
            'label' => 'App settings',
            'icon' => 'bx-slider-alt',
            'description' => 'Branding, locale, approvals & application keys',
        ],
        'audit-logs' => [
            'label' => 'Audit logs',
            'icon' => 'bx-list-check',
            'description' => 'Activity history, exports & compliance review',
        ],
    ];

    public function index(Request $request, string $tab = 'jobs'): View|RedirectResponse|StreamedResponse
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to system configuration');
        }

        if (! array_key_exists($tab, self::TABS)) {
            return redirect()->route('system-configs.index', ['tab' => 'jobs']);
        }

        if ($tab === 'audit-logs' && $request->has('export')) {
            return app(AuditLogsController::class)->exportRequest($request);
        }

        $panelData = $this->panelData($tab, $request);
        $panelData['embedded'] = true;

        return view('system-configs.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'panelData' => $panelData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function panelData(string $tab, Request $request): array
    {
        return match ($tab) {
            'jobs' => [],
            'monitor' => app(SystemdMonitorController::class)->getIndexData(),
            'app-settings' => app(SystemSettingsController::class)->getIndexData(),
            'audit-logs' => app(AuditLogsController::class)->getIndexData($request),
            default => [],
        };
    }
}
