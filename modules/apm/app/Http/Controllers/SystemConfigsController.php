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
        'backups' => [
            'label' => 'Database backups',
            'icon' => 'bx-data',
            'description' => 'Scheduled backups, retention policy & archive delivery',
        ],
        'stale-memos' => [
            'label' => 'Stale memos',
            'icon' => 'bx-archive-in',
            'description' => 'Auto-archive abandoned draft memos holding budget & archive history',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp',
            'icon' => 'bxl-whatsapp',
            'description' => 'WhatsApp bot connection, bot number & staff group integration',
        ],
    ];

    public function index(Request $request, string $tab = 'jobs'): View|RedirectResponse|StreamedResponse
    {
        if ($tab === 'whatsapp') {
            if (! whatsapp_config_can_access()) {
                abort(403, 'Unauthorized access to WhatsApp configuration.');
            }
        } elseif (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to system configuration');
        }

        $tabs = $this->visibleTabs();

        if (! array_key_exists($tab, $tabs)) {
            $defaultTab = array_key_first($tabs) ?: 'jobs';

            return redirect()->route('system-configs.index', ['tab' => $defaultTab]);
        }

        if ($tab === 'audit-logs' && $request->has('export')) {
            return app(AuditLogsController::class)->exportRequest($request);
        }

        $panelData = $this->panelData($tab, $request);
        $panelData['embedded'] = true;

        return view('system-configs.index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'panelData' => $panelData,
        ]);
    }

    /**
     * @return array<string, array{label: string, icon: string, description: string}>
     */
    public function visibleTabs(): array
    {
        if (in_array(89, user_session('permissions', []))) {
            return self::TABS;
        }

        if (whatsapp_config_can_access()) {
            return ['whatsapp' => self::TABS['whatsapp']];
        }

        return [];
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
            'backups' => app(BackupController::class)->getIndexData(),
            'stale-memos' => app(StaleMemoArchivesController::class)->getIndexData($request),
            'whatsapp' => app(WhatsAppSettingsController::class)->getIndexData(),
            default => [],
        };
    }
}
